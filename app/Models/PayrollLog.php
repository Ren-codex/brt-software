<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollLog extends Model
{
    protected $fillable = [
        'payroll_id',
        'action',
        'actioned_by_id',
        'remarks',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function actionedBy()
    {
        return $this->belongsTo(User::class, 'actioned_by_id');
    }
}
