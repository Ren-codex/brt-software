<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('credit:monthly')->monthlyOn(1, '01:00');
Schedule::command('credit:annual')->yearlyOn(1, 1, '01:00');
// Schedule::command('notify:schedule')->weekdays()->dailyAt('08:00');
// Schedule::command('notify:schedule')->weekdays()->dailyAt('08:00');
// Schedule::command('notify:due-tsrs')->weekdays()->dailyAt('12:00');