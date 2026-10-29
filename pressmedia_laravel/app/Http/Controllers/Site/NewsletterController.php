<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterConfirm;
use App\Models\NewsletterSubscriber;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/** Public newsletter forms of the prototype (modal and article box), with double opt-in. */
class NewsletterController extends Controller
{
    public function store(Request $request)
    {
        $ok = fn () => response()->json(['message' => __('site.nl_pending')], 201);
        if (filled($request->input('website'))) {
            return $ok();   // honeypot: answer as usual, store nothing
        }
        // The prototype's checkboxes are named "topics" (not "topics[]"): accept one value or a list.
        if ($request->has('topics') && ! is_array($request->input('topics'))) {
            $request->merge(['topics' => [$request->input('topics')]]);
        }
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'consent' => ['sometimes', 'accepted'],   // only the article box shows the checkbox
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string', 'in:daily,alerts,full'],
        ]);
        $sub = NewsletterSubscriber::firstOrNew(['email' => strtolower($data['email'])]);
        // Already confirmed: say the same thing, send nothing (no way to probe who is subscribed).
        if ($sub->exists && $sub->status === 'confirmed') {
            return $ok();
        }
        $sub->fill(['locale' => app()->getLocale(), 'topics' => $data['topics'] ?? ['daily'], 'status' => 'pending',
            'source' => $request->boolean('consent') ? 'article' : 'site', 'consent_at' => now(), 'unsubscribed_at' => null])->save();
        try {
            Mail::to($sub->email)->send(new NewsletterConfirm($sub));
        } catch (Throwable $e) {
            report($e);
        }

        return $ok();
    }

    public function confirm(string $token): View
    {
        $sub = NewsletterSubscriber::where('token', $token)->firstOrFail();
        if ($sub->status !== 'confirmed') {
            $sub->update(['status' => 'confirmed', 'confirmed_at' => now(), 'unsubscribed_at' => null]);
        }

        return $this->page(__('site.nl_confirmed'));
    }

    public function unsubscribe(string $token): View
    {
        $sub = NewsletterSubscriber::where('token', $token)->firstOrFail();
        $sub->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);

        return $this->page(__('site.nl_unsubscribed'));
    }

    private function page(string $message): View
    {
        $lang = app()->getLocale();

        return view('site.message', ['message' => $message, 'bodyClass' => 'page-legal',
            'meta' => ['title' => 'Newsletter'.Seo::SUFFIX, 'description' => $message, 'canonical' => url("$lang/index.html"), 'robots' => 'noindex,nofollow'],
            'alternates' => ['fr' => url('fr/index.html'), 'en' => url('en/index.html')]]);
    }
}
