<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankReconciliationClear extends Model
{
    protected $fillable = ['reconciliation_id', 'journal_entry_line_id'];

    public function reconciliation()
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function journalEntryLine()
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_entry_line_id');
    }
}
