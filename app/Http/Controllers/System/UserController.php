<?php

namespace App\Http\Controllers\System;

use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\System\User\UserClass;
use App\Http\Requests\System\UserRequest;
use App\Http\Requests\System\UserRoleRequest;

class UserController extends Controller
{
    use HandlesTransaction;

    public $user,$dropdown;

    public function __construct(UserClass $user, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->user = $user;
    }

    public function index(Request $request){
        switch($request->option){
            case 'list':
                return $this->user->list($request);
            break;
            case 'authentication-logs':
                return $this->user->authentication($request);
            break;
            case 'activity-logs':
                return $this->user->activity($request);
            break;
            default:
                return inertia('Modules/Accounts/Users/Index',[
                    'dropdowns' => [
                        'roles' => $this->dropdown->roles()
                    ]
                ]); 
        }   
    }

     public function update($id, UserRequest $request ){
        $result = $this->handleTransaction(function () use ($request) {
            switch($request->option){
                case 'users':
                    return $this->user->update($request);
                break;
                case 'reset_password':
                    return $this->user->resetPassword($request);
                break;
                case 'deactivate':
                    return $this->user->deactivate($request);
                break;
                case 'status':
                    return $this->user->status($request);
                break;
                case 'credential':
                    return $this->user->credential($request);
                break;
                case 'role':
                    return $this->user->user_role($request);
                break;
                case 'set_role_active':
                    return $this->user->user_role($request);
                break;
            }
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function store(UserRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->user->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

     public function show($code){
        return inertia('Modules/System/Users/View',[
            'user_data' => $this->user->view($code),
            'dropdowns' => [
               'roles' => $this->dropdown->roles()
            ],
        ]);
    }
}
