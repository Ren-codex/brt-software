<?php

namespace App\Models;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class UserProfile extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];
    protected $fillable = [
        'lastname', 
        'firstname',
        'middlename',
        'mobile',
        'avatar',
        'signature',
        'is_soloparent',
        'birthdate',
        'birthmonth',
        'sex_id',
        'suffix_id',
        'marital_id',
        'religion_id',
        'blood_id',    
        'user_id', 
    ];
    protected $appends = ['name','fullname'];
    protected $encryptable = [
        'firstname',
        'middlename',
        'mobile',
        'birthdate',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function sex()      { return $this->belongsTo(ListData::class, 'sex_id'); }
    public function suffix()   { return $this->belongsTo(ListData::class, 'suffix_id'); }
    public function blood()    { return $this->belongsTo(ListData::class, 'blood_id'); }
    public function marital()  { return $this->belongsTo(ListData::class, 'marital_id'); }
    public function religion() { return $this->belongsTo(ListData::class, 'religion_id'); }

    public function getFullnameAttribute()
    {
        $middleInitial = $this->middlename ? strtoupper($this->middlename[0]) . '.' : '';
        $name = trim("{$this->firstname} {$middleInitial} {$this->lastname}");
        if ($this->suffix?->name) {
            $name .= ', ' . $this->suffix->name;
        }
        return $name;
    }

    public function getNameAttribute()
    {
        $middleInitial = $this->middlename ? strtoupper($this->middlename[0]) . '.' : '';
        $parts = [trim($this->lastname) . ',', trim($this->firstname), $middleInitial, $this->suffix?->name];
        return implode(' ', array_filter($parts));
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, ['firstname', 'middlename', 'lastname']) && !is_null($value)) {
            $value = ucfirst(strtolower($value));
        }

        if (in_array($key, $this->encryptable) && !is_null($value) && $value !== '') {
            $value = Crypt::encryptString($value);
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if (in_array($key, $this->encryptable) && !is_null($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                return $value;
            }
        }
        return $value;
    }

    protected static $recordEvents = ['updated'];
    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()
        ->logOnly([
            'firstname',
            'lastname',
            'middlename',
            'suffix_id',
            'sex_id',
            'birthdate',
            'birthmonth',
            'mobile',
            'marital_id',
            'religion_id',
            'blood_id',
            'signature',
            'avatar'
        ])
        ->setDescriptionForEvent(fn(string $eventName) => "$eventName the profile information")
        ->useLogName('User Profile')
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
    }
}
