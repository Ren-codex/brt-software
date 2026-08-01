<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('credit:monthly')->monthlyOn(1, '01:00');
Schedule::command('credit:annual')->yearlyOn(1, 1, '01:00');
Schedule::command('invoices:mark-overdue')->dailyAt('00:05');
Schedule::command('expense:carry-budget')->monthlyOn(1, '00:05');