<?php

namespace App\Models;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Employee extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];
    protected $fillable = [
        'lastname',
        'firstname',
        'middlename',
        'email',
        'mobile',
        'avatar',
        'birthdate',
        'sex',
        'address',
        'suffix',
        'religion',
        'position_id',
        'is_regular',
        'is_active',
        'is_blacklisted',
        'added_by_id',
        'user_id',
    ];
    protected $appends = ['fullname'];
    public function user()     { return $this->belongsTo(User::class); }
    public function position() { return $this->belongsTo(ListPosition::class, 'position_id'); }

    // Note: sex, religion, suffix are stored as strings, not foreign keys

    public function getFullnameAttribute()
    {
        $middleInitial = $this->middlename ? strtoupper($this->middlename[0]) . '.' : '';
        $name = trim("{$this->firstname} {$middleInitial} {$this->lastname}");
        if ($this->suffix) {
            $name .= ', ' . $this->suffix;
        }
        return $name;
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, ['firstname', 'middlename', 'lastname']) && !is_null($value)) {
            $value = ucfirst(strtolower($value));
        }

        return parent::setAttribute($key, $value);
    }

    protected static $recordEvents = ['updated'];
    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()
        ->logOnly([
            'firstname',
            'lastname',
            'middlename',
            'suffix',
            'sex',
            'birthdate',
            'mobile',
            'religion',
            'address',
            'position_id',
            'avatar'
        ])
        ->setDescriptionForEvent(function(string $eventName) {
            return "$eventName the Employee information";
        })
        ->useLogName('Employee')
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
    }

    
    public function added_by()
    {
        return $this->belongsTo('App\Models\User', 'added_by_id');
    }
}
