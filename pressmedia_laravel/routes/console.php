<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('posts:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('newsletter:send')->everyMinute()->withoutOverlapping();
// Backups (EX-29): database every night, uploaded media weekly (Benin time).
Schedule::command('backup:run')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('backup:run --media')->weeklyOn(0, '04:15')->withoutOverlapping();
