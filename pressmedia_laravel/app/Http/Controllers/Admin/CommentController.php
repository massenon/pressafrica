<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Moderation queue (EX-26): pending first; approve, mark as spam, or delete (archived). */
class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(Comment::STATUSES)]])['status'] ?? 'pending';
        $comments = Comment::with('post.translations')->where('status', $status)->latest('id')->paginate(30)->withQueryString();
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

    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();
        AuditLog::record('delete', 'comments', 'comment:'.$comment->id);

        return back()->with('status', 'Comment deleted.');
    }
}
