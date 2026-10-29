<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\ContactReceived;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Contact page form (prototype fields), stored and forwarded to the support inbox (config mail.support). */
class ContactController extends Controller
{
    public function store(Request $request)
    {
        $ok = fn () => response()->json(['message' => __('site.ct_ok')], 201);
        if (filled($request->input('website'))) {
            return $ok();
        }
        $d = $request->validate([
            'fname' => ['required', 'string', 'max:80'], 'lname' => ['required', 'string', 'max:80'], 'org' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'], 'tel' => ['nullable', 'string', 'max:40', 'regex:/^[+()\d][\d\s().-]{5,}$/'],
            'country' => ['nullable', 'string', 'max:80'], 'city' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:5000'], 'consent' => ['accepted'], 'terms' => ['nullable'],
        ]);
        $text = strip_tags($d['message']);
        $msg = ContactMessage::create(['locale' => app()->getLocale(), 'first_name' => strip_tags($d['fname']), 'last_name' => strip_tags($d['lname']),
            'organisation' => $d['org'] ?? null, 'email' => $d['email'], 'phone' => $d['tel'] ?? null, 'country' => $d['country'] ?? null, 'city' => $d['city'] ?? null,
            'message' => $text, 'consent_at' => now(),
            // Link-stuffed messages are kept for review but not forwarded (S5 anti-spam rule, as for comments).
            'status' => preg_match_all('#https?://|www\.#i', $text) >= 3 ? 'spam' : 'new']);

        if ($msg->status === 'new' && config('mail.support')) {
            try {
                Mail::to(config('mail.support'))->send(new ContactReceived($msg));
            } catch (Throwable $e) {
                report($e);   // the message is stored either way and visible in Admin > Messages
            }
        }

        return $ok();
    }
}
