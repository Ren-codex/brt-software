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
        'hours_per_day',
        'overtime_rate',
        'is_regular',
        'is_active',
        'is_blacklisted',
        'added_by_id',
        'user_id',
    ];
    protected $appends = ['fullname'];
    public function user()     { return $this->belongsTo(User::class); }
    public function position() { return $this->belongsTo(ListPosition::class, 'position_id'); }

    /**
     * The other jobs this person covers.
     *
     * Never the one that pays — that is position() above, and payroll reads
     * only that. These say who may be picked as a driver, a rep, and so on,
     * for somebody whose main job is something else.
     */
    public function extraPositions()
    {
        return $this->belongsToMany(ListPosition::class, 'employee_positions', 'employee_id', 'position_id')->withTimestamps();
    }

    /**
     * Set the other jobs this person covers.
     *
     * The paying position is dropped if it is passed in: holding it twice
     * says nothing and would print the title twice beside their name.
     */
    public function syncExtraPositions($positionIds): void
    {
        $ids = collect($positionIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->reject(fn ($id) => $id === (int) $this->position_id)
            ->unique()
            ->values()
            ->all();

        $this->extraPositions()->sync($ids);
    }

    /** Every position this person holds, the paying one first. */
    public function allPositionIds(): array
    {
        return collect([$this->position_id])
            ->merge($this->extraPositions->pluck('id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // Note: sex, religion, suffix are stored as strings, not foreign keys

    public function getFullnameAttribute()
    {
        $middleInitial = $this->middlename ? strtoupper($this->middlename[0]) . '.' : '';
        // Joined from the parts that are actually there — interpolating a blank
        // middle initial left a double space in the name of every employee
        // without a middle name.
        $parts = array_filter(array_map('trim', [$this->firstname, $middleInitial, $this->lastname]), 'strlen');
        $name = implode(' ', $parts);
        if ($this->suffix) {
            $name .= ', ' . $this->suffix;
        }
        return $name;
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, ['firstname', 'middlename', 'lastname']) && !is_null($value)) {
            // Capitalize every word, not just the first: ucfirst() stored
            // "Dela Cruz" as "Dela cruz". The extra delimiters keep hyphenated
            // and apostrophe names right ("Reyes-Lim", "O'Brien"). Trim first,
            // because capitalizing a leading space left the name lowercase.
            $value = ucwords(strtolower(trim($value)), " -'");
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
            'hours_per_day',
            'overtime_rate',
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

    public function loans()
    {
        return $this->hasMany('App\Models\Loan', 'employee_id');
    }
}
