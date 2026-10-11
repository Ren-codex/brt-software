<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The payroll status shown as "Approval" reads as an action rather than a
 * state. It is a payroll waiting on an approver, so it is labelled the way its
 * neighbours are — For Payment, For Release, For Verification.
 *
 * Only the label changes. Everything in the code finds this status by its slug
 * ('approval'), which is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('list_statuses')->where('slug', 'approval')->update(['name' => 'For Approval']);
    }

    public function down(): void
    {
        DB::table('list_statuses')->where('slug', 'approval')->update(['name' => 'Approval']);
    }
};
