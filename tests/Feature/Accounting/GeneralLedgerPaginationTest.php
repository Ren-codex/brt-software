<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralLedgerPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->account = Account::create([
            'code'      => '1001',
            'slug'      => 'cash',
            'name'      => 'Cash',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            $entry = JournalEntry::create([
                'journal_number' => 'JE-TEST-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'entry_date'     => '2026-01-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'entry_type'     => 'manual',
                'status'         => 'posted',
                'posted_at'      => now(),
            ]);
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $this->account->id,
                'line_type'        => 'debit',
                'amount'           => 100.00,
                'line_order'       => 1,
            ]);
        }
    }

    public function test_journal_lines_returns_paginated_envelope(): void
    {
        $response = $this->getJson('/accounting/general-ledger?option=journal_lines&per_page=10');

        $response->assertOk();
        $response->assertJsonStructure([
            'data'  => [['id', 'journal_number', 'entry_date', 'account_code', 'account_name', 'debit', 'credit']],
            'meta'  => ['current_page', 'last_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
            'stats' => ['total_debits', 'total_credits', 'is_balanced', 'entry_count', 'line_count'],
        ]);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 12);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonCount(10, 'data');
    }

    public function test_per_page_param_is_respected(): void
    {
        $response = $this->getJson('/accounting/general-ledger?option=journal_lines&per_page=5');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 5);
        $response->assertJsonPath('meta.last_page', 3);
        $response->assertJsonCount(5, 'data');
    }

    public function test_per_page_is_clamped_to_100(): void
    {
        $response = $this->getJson('/accounting/general-ledger?option=journal_lines&per_page=500');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 100);
    }

    public function test_stats_reflect_full_dataset_not_current_page(): void
    {
        $response = $this->getJson('/accounting/general-ledger?option=journal_lines&per_page=10');

        $response->assertOk();
        $response->assertJsonPath('stats.line_count', 12);
    }
}
