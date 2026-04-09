<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'code',
        'slug',
        'name',
        'type',
        'subtype',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function journalEntryLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
