<?php

use App\Jobs\Pangolin\PollCluster;
use App\Jobs\Authentik\PollCluster as AuthentikPollCluster;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new PollCluster)->everyMinute();
Schedule::job(new AuthentikPollCluster)->everyMinute();
