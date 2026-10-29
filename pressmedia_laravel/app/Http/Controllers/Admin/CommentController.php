<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CommentReplied;
use App\Models\AuditLog;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Moderation queue (EX-26): pending first; approve, mark as spam, or delete (archived). */
class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(Comment::STATUSES)]])['status'] ?? 'pending';
        $comments = Comment::with(['post.translations', 'parent', 'replies' => fn ($q) => $q->whereNotNull('staff_user_id')])->where('status', $status)->latest('id')->paginate(30)->withQueryString();
        $counts = Comment::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.comments.index', compact('comments', 'status', 'counts'));
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(Comment::STATUSES)]])['status'];
        // Approving publishes reader text on the site, so it needs the publish right; spam/pending only edit.
        abort_unless($request->user()->can($status === 'approved' ? 'comments.publish' : 'comments.edit'), 403);

        $before = $comment->status;
        $comment->update(['status' => $status, 'moderated_by' => $request->user()->id]);
        AuditLog::record($status === 'approved' ? 'approve' : 'update', 'comments', 'comment:'.$comment->id, "status: $before → $status");

        return back()->with('status', 'Comment updated.');
    }

    /**
     * Public newsroom answer under a reader's comment (spec EX-44): published at once, signed with the staff
     * member's name and the "Rédaction" badge; approves a pending question in the same step; e-mails the reader
     * if they asked for it (S4).
     */
    public function reply(Request $request, Comment $comment): RedirectResponse
    {
        abort_if($comment->parent_id !== null, 422, 'Replies are one level deep.');
        $body = $request->validate(['body' => ['required', 'string', 'max:2000']])['body'];
        $user = $request->user();

        if ($comment->status !== 'approved') {
            $comment->update(['status' => 'approved', 'moderated_by' => $user->id]);
            AuditLog::record('approve', 'comments', 'comment:'.$comment->id, 'approved by replying');
        }
        $reply = Comment::create(['post_id' => $comment->post_id, 'parent_id' => $comment->id, 'locale' => $comment->locale,
            'author' => $user->name, 'email' => $user->email, 'body' => strip_tags($body), 'status' => 'approved',
            'consent_at' => now(), 'moderated_by' => $user->id, 'staff_user_id' => $user->id]);
        AuditLog::record('reply', 'comments', 'comment:'.$comment->id, 'reply:'.$reply->id);

        // The answer is public whatever happens to the e-mail: an unreachable mail server is logged, not shown as a failure.
        $mailed = false;
        if ($comment->notify_consent_at) {
            try {
                Mail::to($comment->email)->send(new CommentReplied($comment->load('post.translations'), $reply->load('staff')));
                $comment->update(['notified_at' => now()]);
                $mailed = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'Reply published'.($comment->notify_consent_at
            ? ($mailed ? ' and the reader was e-mailed.' : '; the e-mail to the reader could not be sent (see the log).') : '.'));
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();
        AuditLog::record('delete', 'comments', 'comment:'.$comment->id);

        return back()->with('status', 'Comment deleted.');
    }
}
