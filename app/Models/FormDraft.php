<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A half-written form a user set aside (see the create_form_drafts migration).
 * The payload is whatever the client serialised; it is handed back verbatim.
 */
class FormDraft extends Model
{
    protected $fillable = [
        'user_id',
        'kind',
        'summary',
        'payload',
        'return_path',
        'saved_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'saved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
