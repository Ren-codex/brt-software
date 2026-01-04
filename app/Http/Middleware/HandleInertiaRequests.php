<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Http\Resources\UserResource;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {   
        $user = $request->user();
        return [
            ...parent::share($request),
            'user' => (\Auth::check()) ? new UserResource(User::with('employee')->where('id',\Auth::user()->id)->first()) : '',
            'roles' => (\Auth::check()) ? \Auth::user()->roles()->where('user_roles.is_active', 1)->pluck('name') : '',
            'flash' => [
                'data' => session('data'),
                'message' => session('message'),
                'info' => session('info'),
                'status' => session('status'),
            ],
        ];
    }
}