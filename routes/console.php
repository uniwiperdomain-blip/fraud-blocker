<?php

use App\Jobs\CleanupExpiredBlocksJob;
use App\Jobs\SyncGoogleAdsExclusionsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fraud protection scheduled jobs
Schedule::job(new SyncGoogleAdsExclusionsJob)->everyFifteenMinutes();
Schedule::job(new CleanupExpiredBlocksJob)->hourly();
Schedule::command('model:prune', ['--model' => \App\Models\FraudLog::class])->daily();
