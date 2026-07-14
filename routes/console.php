<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\ProcessTrackingReload;
use App\Jobs\SyncGoogleSheetOrders;
use App\Models\GoogleSheetConnection;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:run-tracking-service')
    ->everyMinute(); // or hourly(), daily(), etc

// Google Sheets sync — runs every 5 minutes, dispatches jobs for eligible connections
Schedule::call(function () {
    $connections = GoogleSheetConnection::where('is_active', true)
        ->where('mapping_confirmed', true)
        ->get();

    foreach ($connections as $connection) {
        // Respect per-connection interval
        $interval = $connection->sync_interval_minutes ?? 10;
        if ($connection->last_synced_at && $connection->last_synced_at->diffInMinutes(now()) < $interval) {
            continue;
        }

        SyncGoogleSheetOrders::dispatch($connection->id);
    }
})->everyFiveMinutes();