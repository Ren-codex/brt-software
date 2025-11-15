<?php

namespace App\Services\Profile;

use Illuminate\Support\Carbon;
use App\Models\AuthenticationLog;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\AuthenticationResource;

class ViewClass
{
    public function authenticationlogs($request){
        $data = AuthenticationLog::with('user.profile')->where('user_id',\Auth::user()->id)->paginate($request->count);
        return AuthenticationResource::collection($data);
    }

    public function activitylogs($request){
        $data = Activity::with('causer.profile')->orderBy('created_at','DESC')->paginate($request->count);
        return ActivityResource::collection($data);
    }

    public function sessions($request){
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return collect(
            \DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', \Auth::user()->id)
                    ->orderBy('last_activity', 'desc')
                    ->get()
        )->map(function ($session) use ($request){
            $agent = $this->createAgent($session);

            return (object) [
                'agent' => [
                    'is_desktop' => $agent->isDesktop(),
                    'platform' => $agent->platform(),
                    'browser' => $agent->browser(),
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        });
    }

    public function statistics($request){
        return [
            [
                'name' => 'Successful Login',
                'icon' => 'ri-checkbox-circle-fill',
                'color' => 'text-success',
                'total' => AuthenticationLog::where('user_id',\Auth::user()->id)->where('is_failed',0)->count()
            ],
            [
                'name' => 'Suspicious Login',
                'icon' => 'ri-error-warning-fill',
                'color' => 'text-warning',
                'total' =>  AuthenticationLog::where('user_id',\Auth::user()->id)->where('is_suspicious',1)->count()
            ],
            [
                'name' => 'Login Attempts',
                'icon' => 'ri-close-circle-fill',
                'color' => 'text-danger',
                'total' =>  AuthenticationLog::where('user_id',\Auth::user()->id)->where('is_failed',1)->count()
            ]
        ];
    }

    protected function createAgent($session)
    {
        return tap(new Agent, function ($agent) use ($session) {
            $agent->setUserAgent($session->user_agent);
        });
    }
}
