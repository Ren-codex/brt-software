<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subjectShortName = $this->subject_type
            ? class_basename($this->subject_type)
            : ($this->log_name ?? 'Record');

        $causerName = null;
        if ($this->causer) {
            $emp = $this->causer->employee;
            $causerName = $emp
                ? trim("{$emp->firstname} {$emp->lastname}")
                : $this->causer->email;
        }

        $eventVerb = match ($this->event) {
            'created'  => 'created',
            'updated'  => 'updated',
            'deleted'  => 'deleted',
            default    => $this->event ?? $this->description,
        };

        $description = $causerName
            ? "{$causerName} {$eventVerb} a {$subjectShortName} record"
            : ucfirst("{$eventVerb} a {$subjectShortName} record");

        return [
            'id'          => $this->id,
            'log_name'    => $subjectShortName,
            'description' => $description,
            'event'       => $this->event ?? $this->description,
            'causer'      => $causerName ?? 'System',
            'properties'  => $this->properties,
            'created_at'  => $this->created_at
                ? $this->created_at->format('M d, Y g:i A')
                : null,
        ];
    }
}
