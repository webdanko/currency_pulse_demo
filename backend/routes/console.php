<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Poll the upstream once a minute; withoutOverlapping guards against a slow
// upstream causing overlapping runs.
Schedule::command('fx:poll')->everyMinute()->withoutOverlapping();
