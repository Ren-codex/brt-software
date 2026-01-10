<?php

namespace App\Models;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, LogsActivity;

    protected $fillable = [
        'username',
        'email',
        'password',
        'is_active',
        'is_new',
        'email_verified_at',
        'password_changed_at',
        'two_factor_confirmed_at',
        'two_factor_secret',
        'two_factor_recovery_codes'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employee()
    {
        return $this->hasOne('App\Models\Employee', 'user_id');
    }


    public function myroles()
    {
        return $this->hasMany('App\Models\UserRole', 'user_id')->orderBy('is_active','DESC');
    }

    public function roles()
    {
        return $this->belongsToMany('App\Models\ListRole', 'user_roles', 'user_id', 'role_id');
    }

    public function hasRole($roleName)
    {
        return $this->roles()->where('name', $roleName)->exists();
    }



    public function getEmailVerifiedAtAttribute($value)
    {
        return $value ? date('M d, Y g:i a', strtotime($value)) : null;
    }

    public function getPasswordChangedAtAttribute($value)
    {
        return $value ? date('F d, Y g:i a', strtotime($value)) : null;
    }

    public function getTwoFactorConfirmedAtAttribute($value)
    {
        return $value ? date('M d, Y g:i a', strtotime($value)) : null;
    }
    
    public function getUpdatedAtAttribute($value)
    {
        return date('M d, Y g:i a', strtotime($value));
    }

    public function getCreatedAtAttribute($value)
    {
        return date('F d, Y g:i a', strtotime($value));
    }

    public function getNameAttribute()
    {
        return $this->username;
    }

    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()
        ->logOnly(['username','kradworkz','is_active'])
        ->setDescriptionForEvent(fn(string $eventName) => "{$eventName} the user information")
        ->useLogName('User')
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
    }
}
