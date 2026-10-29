<?php

use App\Http\Controllers\Admin\AdZoneController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MediaItemController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PrivacyController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Site\AdClickController;
use App\Http\Controllers\Site\ArticleController;
use App\Http\Controllers\Site\CommentController as PublicCommentController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\NewsletterController;
use App\Http\Controllers\Site\SectionController;
use App\Http\Controllers\Site\SeoController;
use App\Http\Middleware\SetLocale;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Root: the visitor's remembered language, else the site default (French). Never Accept-Language (INV-07).
Route::get('/', function (Request $request) {
    $saved = $request->cookie('locale');

    return redirect('/'.(in_array($saved, SetLocale::LOCALES, true) ? $saved : Settings::get('defaultLang')).'/index.html');
});

Route::get('ad/{zone}/{device}', AdClickController::class)->middleware('throttle:60,1')->name('ads.click');
Route::get('robots.txt', [SeoController::class, 'robots']);
Route::get('sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('news-sitemap.xml', [SeoController::class, 'newsSitemap']);

Route::prefix('{locale}')->whereIn('locale', SetLocale::LOCALES)->middleware(['locale', 'count'])->group(function () {
    Route::get('/', fn () => redirect('/'.app()->getLocale().'/index.html', 301));
    Route::get('index.html', [HomeController::class, 'show'])->name('home');
    Route::get('feed.xml', [SeoController::class, 'feed'])->name('feed');
    Route::get('articles/{slug}.html', [ArticleController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('articles.show');
    Route::get('{slug}.html', [SectionController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('sections.show');   // keep last: catches /{locale}/*.html
    Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:comments')->name('contact.store');
    Route::post('newsletter', [NewsletterController::class, 'store'])->middleware('throttle:comments')->name('newsletter.store');
    Route::get('newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->where('token', '[A-Za-z0-9]+')->name('newsletter.confirm');
    Route::get('newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]+')->name('newsletter.unsubscribe');
    Route::post('articles/{slug}/comments', [PublicCommentController::class, 'store'])->middleware('throttle:comments')->name('comments.store');
});

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
    });

    Route::middleware('auth')->name('admin.')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::get('audit', [AuditLogController::class, 'index'])->middleware('can:audit.view')->name('audit.index');

        // Posts: fine-grained checks (own posts vs all) live in PostPolicy.
        Route::resource('posts', PostController::class)->except('show');

        Route::get('categories', [CategoryController::class, 'index'])->middleware('can:categories.view')->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('can:categories.create')->name('categories.store');
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->middleware('can:categories.edit')->name('categories.edit');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('can:categories.edit')->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('can:categories.delete')->name('categories.destroy');

        Route::get('media', [MediaController::class, 'index'])->middleware('can:media.view')->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->middleware('can:media.create')->name('media.store');
        Route::put('media/{medium}', [MediaController::class, 'update'])->middleware('can:media.edit')->name('media.update');
        Route::delete('media/{medium}', [MediaController::class, 'destroy'])->middleware('can:media.delete')->name('media.destroy');

        Route::get('comments', [CommentController::class, 'index'])->middleware('can:comments.view')->name('comments.index');
        Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');   // right checked per status
        Route::post('comments/{comment}/reply', [CommentController::class, 'reply'])->middleware('can:comments.publish')->name('comments.reply');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->middleware('can:comments.delete')->name('comments.destroy');

        // Featured: home carousel (spec EX-41).
        Route::get('featured', [HeroSlideController::class, 'index'])->middleware('can:featured.view')->name('featured.index');
        Route::get('featured/create', [HeroSlideController::class, 'create'])->middleware('can:featured.create')->name('featured.create');
        Route::post('featured', [HeroSlideController::class, 'store'])->middleware('can:featured.create')->name('featured.store');
        Route::post('featured/restore', [HeroSlideController::class, 'restore'])->middleware('can:featured.restore')->name('featured.restore');
        Route::get('featured/{slide}/edit', [HeroSlideController::class, 'edit'])->middleware('can:featured.edit')->name('featured.edit');
        Route::put('featured/{slide}', [HeroSlideController::class, 'update'])->middleware('can:featured.edit')->name('featured.update');
        Route::post('featured/{slide}/move', [HeroSlideController::class, 'move'])->middleware('can:featured.edit')->name('featured.move');
        Route::delete('featured/{slide}', [HeroSlideController::class, 'destroy'])->middleware('can:featured.delete')->name('featured.destroy');

        Route::get('analytics', [AnalyticsController::class, 'index'])->middleware('can:analytics.view')->name('analytics.index');
        Route::get('analytics/export/{format}', [AnalyticsController::class, 'export'])->middleware('can:analytics.view')->name('analytics.export');

        Route::get('campaigns', [CampaignController::class, 'index'])->middleware('can:newsletter.view')->name('campaigns.index');
        Route::get('campaigns/create', [CampaignController::class, 'create'])->middleware('can:newsletter.create')->name('campaigns.create');
        Route::post('campaigns', [CampaignController::class, 'store'])->middleware('can:newsletter.create')->name('campaigns.store');
        Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->middleware('can:newsletter.view')->name('campaigns.edit');
        Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('can:newsletter.edit')->name('campaigns.update');
        Route::post('campaigns/{campaign}/test', [CampaignController::class, 'test'])->middleware('can:newsletter.edit')->name('campaigns.test');
        Route::post('campaigns/{campaign}/schedule', [CampaignController::class, 'schedule'])->middleware('can:newsletter.publish')->name('campaigns.schedule');
        Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->middleware('can:newsletter.publish')->name('campaigns.cancel');
        Route::get('newsletter', [AdminNewsletterController::class, 'index'])->middleware('can:newsletter.view')->name('newsletter.index');
        Route::get('newsletter/export/{format}', [AdminNewsletterController::class, 'export'])->middleware('can:newsletter.view')->name('newsletter.export');
        Route::post('newsletter/import', [AdminNewsletterController::class, 'import'])->middleware('can:newsletter.create')->name('newsletter.import');
        Route::delete('newsletter/{subscriber}', [AdminNewsletterController::class, 'destroy'])->middleware('can:newsletter.delete')->name('newsletter.destroy');

        Route::get('ads', [AdZoneController::class, 'index'])->middleware('can:ads.view')->name('ads.index');
        Route::get('ads/{zone}/edit', [AdZoneController::class, 'edit'])->middleware('can:ads.edit')->name('ads.edit');
        Route::put('ads/{zone}', [AdZoneController::class, 'update'])->middleware('can:ads.edit')->name('ads.update');

        Route::get('messages', [MessageController::class, 'index'])->middleware('can:messages.view')->name('messages.index');
        Route::get('messages/{message}', [MessageController::class, 'show'])->middleware('can:messages.view')->name('messages.show');
        Route::post('messages/{message}/reply', [MessageController::class, 'reply'])->middleware('can:messages.edit')->name('messages.reply');
        Route::put('messages/{message}', [MessageController::class, 'update'])->middleware('can:messages.edit')->name('messages.update');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])->middleware('can:messages.delete')->name('messages.destroy');

        Route::get('privacy', [PrivacyController::class, 'index'])->middleware('can:users.edit')->name('privacy.index');
        Route::get('privacy/export', [PrivacyController::class, 'export'])->middleware('can:users.edit')->name('privacy.export');
        Route::post('privacy/erase', [PrivacyController::class, 'erase'])->middleware('can:users.edit')->name('privacy.erase');

        // Menus: main menu and mega panels.
        Route::get('menus', [MenuController::class, 'index'])->middleware('can:menus.view')->name('menus.index');
        Route::get('menus/create', [MenuController::class, 'create'])->middleware('can:menus.create')->name('menus.create');
        Route::post('menus', [MenuController::class, 'store'])->middleware('can:menus.create')->name('menus.store');
        Route::post('menus/restore', [MenuController::class, 'restore'])->middleware('can:menus.edit')->name('menus.restore');
        Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->middleware('can:menus.edit')->name('menus.edit');
        Route::put('menus/{menu}', [MenuController::class, 'update'])->middleware('can:menus.edit')->name('menus.update');
        Route::post('menus/{menu}/move', [MenuController::class, 'move'])->middleware('can:menus.edit')->name('menus.move');
        Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->middleware('can:menus.delete')->name('menus.destroy');

        // Media sections (podcasts, videos, magazine, radios, TV); rights checked per type in the controller.
        Route::prefix('media-items/{type}')->whereIn('type', array_keys(\App\Models\MediaItem::TYPES))->name('media-items.')->group(function () {
            Route::get('/', [MediaItemController::class, 'index'])->name('index');
            Route::get('create', [MediaItemController::class, 'create'])->name('create');
            Route::post('/', [MediaItemController::class, 'store'])->name('store');
            Route::get('{item}/edit', [MediaItemController::class, 'edit'])->name('edit');
            Route::put('{item}', [MediaItemController::class, 'update'])->name('update');
            Route::delete('{item}', [MediaItemController::class, 'destroy'])->name('destroy');
        });

        Route::get('tags', [TagController::class, 'index'])->middleware('can:tags.view')->name('tags.index');
        Route::post('tags', [TagController::class, 'store'])->middleware('can:tags.create')->name('tags.store');
        Route::put('tags/{tag}', [TagController::class, 'update'])->middleware('can:tags.edit')->name('tags.update');
        Route::post('tags/{tag}/merge', [TagController::class, 'merge'])->middleware('can:tags.edit')->name('tags.merge');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->middleware('can:tags.delete')->name('tags.destroy');

        Route::get('settings', [SettingsController::class, 'edit'])->middleware('can:settings.view')->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->middleware('can:settings.edit')->name('settings.update');

        Route::get('roles', [RoleController::class, 'index'])->middleware('can:users.view')->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->middleware('can:users.create')->name('roles.store');
        Route::put('roles/{role:name}', [RoleController::class, 'update'])->middleware('can:users.edit')->name('roles.update');
        Route::delete('roles/{role:name}', [RoleController::class, 'destroy'])->middleware('can:users.delete')->name('roles.destroy');
    });
});
