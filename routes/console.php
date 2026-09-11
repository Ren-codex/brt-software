<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('credit:monthly')->monthlyOn(1, '01:00');
Schedule::command('credit:annual')->yearlyOn(1, 1, '01:00');
Schedule::command('invoices:mark-overdue')->dailyAt('00:05');
Schedule::command('sales-orders:notify-unpaid-same-day')->dailyAt('16:00');
Schedule::command('checks:mark-matured')->dailyAt('00:10');
Schedule::command('checks:remind-pending')->dailyAt('09:00');
Schedule::command('deposits:post-due-checks')->dailyAt('00:15');