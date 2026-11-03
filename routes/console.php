<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ayam:update-nama-akun')
    ->dailyAt('00:05') // tiap hari jam 00:05 dini hari
    ->withoutOverlapping()
    ->runInBackground();

// Buka tombol setoran di app satpam tiap pagi (jam di .env: SATPAM_BUKA_OTOMATIS, kosongkan untuk mematikan).
if ($jamBuka = config('satpam.buka_otomatis')) {
    Schedule::command('satpam:buka-setoran')
        ->dailyAt($jamBuka)
        ->timezone('Asia/Jakarta')
        ->withoutOverlapping();
}