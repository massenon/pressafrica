<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\PersonalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin > Personal data: answer access and erasure requests by e-mail address (GDPR, EX-34). */
class PrivacyController extends Controller
{
    public function index(Request $request): View
    {
        $email = $request->validate(['email' => ['nullable', 'email', 'max:150']])['email'] ?? null;

        return view('admin.privacy.index', ['email' => $email, 'data' => $email ? PersonalData::find($email) : null]);
    }

    public function export(Request $request): JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'email', 'max:150']])['email'];
        AuditLog::record('export', 'privacy', 'subject:'.substr(hash('sha256', strtolower($email)), 0, 12));

        return response()->json(PersonalData::find($email) + ['exported_at' => now()->toIso8601String()], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', 'attachment; filename=personal-data.json');
    }

    public function erase(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150'], 'confirm' => ['required', 'same:email']]);
        $done = PersonalData::erase($data['email']);

        return redirect()->route('admin.privacy.index')->with('status', "Erased: {$done['comments_anonymised']} comment(s) anonymised, {$done['newsletter_deleted']} subscription(s) and {$done['contact_messages_deleted']} message(s) deleted.");
    }
}
