<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ContactReply;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Admin > Messages: contact-page inbox with an e-mail reply from the dashboard. */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['new', 'read', 'replied', 'spam'])]])['status'] ?? null;

        return view('admin.messages.index', [
            'messages' => ContactMessage::when($status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'spam'))->latest('id')->paginate(30)->withQueryString(),
            'counts' => ContactMessage::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'status' => $status,
        ]);
    }

    public function show(ContactMessage $message): View
    {
        if ($message->status === 'new') {
            $message->update(['status' => 'read']);
        }

        return view('admin.messages.show', ['m' => $message]);
    }

    public function reply(Request $request, ContactMessage $message): RedirectResponse
    {
        $body = $request->validate(['body' => ['required', 'string', 'max:5000']])['body'];
        try {
            Mail::to($message->email)->send(new ContactReply($message, $body, $request->user()->name));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['body' => 'The e-mail could not be sent; nothing was recorded. Check the mail settings of the host.']);
        }
        $message->update(['status' => 'replied', 'replied_at' => now(), 'replied_by' => $request->user()->id]);
        AuditLog::record('reply', 'messages', 'message:'.$message->id);

        return redirect()->route('admin.messages.index')->with('status', 'Reply sent to '.$message->email.'.');
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(['read', 'spam'])]])['status'];
        $message->update(['status' => $status]);
        AuditLog::record('update', 'messages', 'message:'.$message->id, "status: $status");

        return back()->with('status', 'Message updated.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();
        AuditLog::record('delete', 'messages', 'message:'.$message->id);

        return redirect()->route('admin.messages.index')->with('status', 'Message deleted.');
    }
}
