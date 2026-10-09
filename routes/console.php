<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('credit:monthly')->monthlyOn(1, '01:00');
Schedule::command('credit:annual')->yearlyOn(1, 1, '01:00');
Schedule::command('invoices:mark-overdue')->dailyAt('00:05');
Schedule::command('sales-orders:notify-unpaid-same-day')->dailyAt('16:00');
// End of day: what has been delivered but uncollected, and whose cash is still out.
Schedule::command('deliveries:notify-outstanding')->dailyAt('17:00');
Schedule::command('checks:mark-matured')->dailyAt('00:10');
Schedule::command('checks:remind-pending')->dailyAt('09:00');
Schedule::command('deposits:post-due-checks')->dailyAt('00:15');

// A nightly copy of the database, at 02:00 so it lands after the midnight
// jobs above have finished writing and captures the day whole.
//
// --only-db on purpose: the files are in git and rebuildable, the data is
// not. It keeps the backup to tens of kilobytes rather than gigabytes of
// vendor and node_modules, which matters on shared hosting.
//
// withoutOverlapping, because a backup that runs long must not have the
// next night's start on top of it.
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Prune afterwards, or the disk fills quietly and the backups stop. The
// retention in config/backup.php keeps every backup for a week, then thins
// out to daily, weekly and monthly.
Schedule::command('backup:clean')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Silence is not success: without this, backups could stop for a month and
// look exactly like backups that are working. This is the only job that
// says anything when they are not.
Schedule::command('backup:monitor')->dailyAt('08:00');