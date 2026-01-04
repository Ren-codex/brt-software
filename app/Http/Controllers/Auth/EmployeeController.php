<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Employee\ViewClass;
use App\Services\Employee\SaveClass;
use App\Http\Requests\EmployeeRequest;
use Illuminate\Validation\Rules\Password;
use App\Services\DropdownClass;

class EmployeeController extends Controller
{
    use HandlesTransaction;

    public function __construct(ViewClass $view, SaveClass $save, DropdownClass $dropdown){
        $this->view = $view;
        $this->save = $save;
        $this->dropdown = $dropdown;
    }

    public function index(Request $request){
        $options = $request->option;
        switch($options){
            case 'authentication-logs':
                return $this->view->authenticationlogs($request);
            break;
            case 'activity-logs':
                return $this->view->activitylogs($request);
            break;
            case 'statistics':
                return $this->view->statistics($request);
            break;
            case 'sessions':
                return $this->view->sessions($request);
            break;
           default:
                return inertia('Modules/Employees/Index', [
                    'dropdowns' => [
                        'positions' => $this->dropdown->positions()
                    ]
                ]);
            break;
        }
    }

    public function store(Request $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->save->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function update(EmloyeeRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->save->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function destroy(Request $request)
    {
        return $this->save->destroy($request);
    }

    public function activation(){
        return inertia('Auth/Activation');
    }

    public function activate(Request $request){
        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()     // must include uppercase and lowercase
                    ->letters()       // must include letters
                    ->numbers()       // must include numbers
                    ->symbols()       // must include symbols
                    ->uncompromised() // checks against data leaks (optional)
            ],
        ]);
        $id = \Auth::user()->id;
        $user = User::findOrFail($id);
        $user->is_active = 1;
        $user->must_change = 0;
        $user->password = bcrypt($validated['password']);
        $user->password_changed_at = now();
        if($user->save()){
            return redirect()->intended(route('dashboard', absolute: false));
        }
    }

}
