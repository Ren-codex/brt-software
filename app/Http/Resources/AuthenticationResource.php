<?php

namespace App\Http\Resources;

use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $agent = new Agent();
        $agent->setUserAgent($this->user_agent);

        if($agent->isTablet()){
            $type = 'tablet';
        }else if($agent->isMobile()){
            $type = 'smartphone';
        }else{
            $type = 'computer';
        }

        return [
            'ip' => ($this->ip_address == '::1') ? '127.0.0.1' : $this->ip_address,
            'device' => $agent->device(),
            'platform' => $agent->platform(),
            'browser' =>  $agent->browser(),
            'is_desktop' => $agent->isDesktop(),
            'is_failed' => $this->is_failed,
            'is_suspicious' => $this->is_suspicious,
            'name' => 'wew',
            'avatar' =>  's',
            'created_at' => date('M d, Y g:i a', strtotime($this->created_at)),
            'type' => $type,
            'location' => $this->place(),
        ];
    }

    // "City, Region, Country" from the stored lookup, or null when there was
    // none (a blank lookup is stored as "[]", which used to render ", , ").
    private function place(): ?string
    {
        $location = json_decode((string) $this->location, true);
        if (! is_array($location) || ! empty($location['default'])) {
            return null;
        }

        $parts = array_filter([
            $location['city'] ?? null,
            $location['state_name'] ?? null,
            $location['country'] ?? null,
        ]);

        return $parts ? implode(', ', array_unique($parts)) : null;
    }
}
