<?php

use App\Jobs\Pangolin\PollCluster;
use App\Jobs\Authentik\PollCluster as AuthentikPollCluster;
use App\Jobs\NetworkLookup\PollAllDevices as NetworkLookupPollAllDevices;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new PollCluster)->everyMinute();
Schedule::job(new AuthentikPollCluster)->everyMinute();

// MAC/ARP tables don't need per-minute freshness like the cluster health
// checks above, and 49 SSH connections a minute would be unnecessary load on
// the switches - config-driven interval, default every 10 minutes.
Schedule::job(new NetworkLookupPollAllDevices)
    ->cron('*/'.config('network-lookup.poll_interval_minutes', 10).' * * * *');
