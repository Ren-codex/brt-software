<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Services\Modules\ReportClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The sales report aggregates a dozen independent sections, two of which read
 * from tables added by later migrations — sales_return_history and
 * sales_order_delivery_refusals. Production runs migrations by hand, so one of
 * those tables can be absent there; a missing table used to throw and 500 the
 * whole /reports?option=summary call, which blanked every section at once and
 * read as "no data for anyone".
 *
 * Each table-dependent section now reads empty where its table is absent,
 * instead of bringing the whole report down.
 */
class ReportSurvivesMissingTablesTest extends TestCase
{
    use RefreshDatabase;

    private function summary(): array
    {
        $this->actingAs(User::factory()->create());

        return (new ReportClass)->summary([
            'from' => today()->subMonth()->toDateString(),
            'to' => today()->addDay()->toDateString(),
            'day' => today()->toDateString(),
            'limit' => 10,
            'location_id' => null,
            'payment_mode' => 'all',
            'report_type' => 'sales-summary',
        ]);
    }

    public function test_the_report_survives_a_missing_delivery_refusals_table(): void
    {
        Schema::dropIfExists('sales_order_delivery_refusals');

        $report = $this->summary();

        $this->assertArrayHasKey('sales_return_report', $report);
        $this->assertSame(0.0, $report['sales_return_report']['refused_quantity']);
    }

    public function test_the_report_survives_a_missing_returns_history_table(): void
    {
        Schema::dropIfExists('sales_order_delivery_refusals');
        Schema::dropIfExists('sales_return_history');

        $report = $this->summary();

        // The whole summary still builds, and the returns section reads empty.
        $this->assertArrayHasKey('sales_return_report', $report);
        $this->assertSame(0.0, $report['sales_return_report']['total_value']);
        $this->assertCount(0, $report['sales_return_report']['items']);

        // The sections that do not depend on those tables still come through —
        // the point of the fix is that one missing table no longer blanks all.
        $this->assertArrayHasKey('payment_summary', $report);
        $this->assertArrayHasKey('top_customers', $report);
    }
}
