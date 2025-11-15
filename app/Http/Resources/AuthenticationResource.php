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

        if($agent->isDesktop()){
            $type = 'computer';
        }else if($agent->isMobile()){
            $type = 'smartphone';
        }else if($agent->isTablet()){
            $type = 'tablet';
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
            'location' => json_decode($this->location)
        ];
    }
}
