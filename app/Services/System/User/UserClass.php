<?php

namespace App\Services\System\User;

use Hashids\Hashids;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use App\Models\AuthenticationLog;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\AuthenticationResource;
use App\Http\Resources\System\User\UserResource;
use App\Http\Resources\System\User\RoleResource;
use App\Http\Resources\System\User\ViewResource;

class UserClass
{
    const DEFAULT_PASSWORD = 'bouyant123';

    public function view($code){
        $data = new ViewResource(
            User::query()
            ->with('employee:user_id,firstname,middlename,lastname,suffix,avatar,mobile')
            ->with('myroles:role_id,id,user_id,added_by_id,removed_by_id,removed_at,created_at,is_active','myroles.role:id,name','myroles.added:id','myroles.added.employee:user_id,firstname,middlename,lastname,suffix','myroles.removed:id','myroles.removed.employee:user_id,firstname,middlename,lastname,suffix')
            ->where('id',$code)->first()
        );
        return $data;
    }

    
    public function save($request){

        $username = $request->username;
        $employee = null;

        if ($request->filled('employee_id')) {
            $employee = Employee::whereNull('user_id')->find($request->employee_id);
            if ($employee) {
                $username = $this->generateUsername($employee);
            }
        }

        $data = User::create([
            'username' =>  $username,
            'email' =>  $request->email,
            'password' =>  bcrypt(self::DEFAULT_PASSWORD),
            'is_active' => 1,
            'email_verified_at' => now(),
        ]);
        $data->must_change = 1;
        $data->save();

        foreach($request->role_ids as $role_id){
            UserRole::create([
                'role_id' =>  $role_id,
                'user_id' =>  $data->id,
                'added_by_id' => \Auth::user()->id,
            ]);
        }

        if ($employee) {
            $employee->update(['user_id' => $data->id]);
        }

        $data = User::with('myroles.role')->find($data->id);

        return [
            'data' => new UserResource($data),
            'message' => 'User created successful!',
            'info' => "Username: {$data->username} — Default password: " . self::DEFAULT_PASSWORD . ". The user will be required to set a new password on first login.",
            'password' => self::DEFAULT_PASSWORD,
        ];
    }

    public function generateUsername(Employee $employee){
        $first = strtolower(substr($employee->firstname, 0, 1));
        $middle = $employee->middlename ? strtolower(substr($employee->middlename, 0, 1)) : '';
        $last = strtolower(substr($employee->lastname, 0, 1));
        $mmdd = Carbon::parse($employee->birthdate)->format('md');

        $base = $first . $middle . $last . $mmdd;
        $username = $base;
        $suffix = 2;
        while (User::where('username', $username)->exists()) {
            $username = $base . '-' . $suffix;
            $suffix++;
        }

        return $username;
    }

    public function update($request){

        $data = User::find($request->id);
        $data->username = $request->username;
        $data->email = $request->email;
        if ($request->password) {
            $data->password = bcrypt($request->password);
        }
        $data->save();

        foreach($request->role_ids as $role_id){
            $userRole = UserRole::where('role_id', $role_id)->where('user_id', $data->id)->first();
            if ($userRole) {
                $userRole->update(['is_active' => 1]);
            } else {
                UserRole::create([
                    'role_id' => $role_id,
                    'user_id' => $data->id,
                    'added_by_id' => \Auth::user()->id,
                    'is_active' => 1
                ]);
            }
        }

        $data = User::with('myroles.role')->find($data->id);

        return [
            'data' => new UserResource($data),
            'message' => 'User update was successful!',
            'info' => "You've successfully updated the selected user."
        ];
    }


    public function authentication($request){
        $hashids = new Hashids('krad',10);
        $id = $hashids->decode($request->code);
        $data = AuthenticationLog::with('user.employee')->where('user_id',$id)->orderBy('created_at','DESC')->paginate($request->count);
        return AuthenticationResource::collection($data);
    }

    public function activity($request){
        $hashids = new Hashids('krad',10);
        $id = $hashids->decode($request->code);
        $data = Activity::with('causer.employee')->where('causer_id',$id)->orderBy('created_at','DESC')->paginate($request->count);
        return ActivityResource::collection($data);
    }

    public function list($request){
        $data = User::with('employee:user_id,firstname,middlename,lastname,suffix,avatar,mobile')
        ->with('roles')
        ->with('myroles:role_id,id,user_id,added_by_id,removed_by_id,removed_at,created_at,is_active','myroles.role:id,name','myroles.added:id','myroles.added.employee:user_id,firstname,middlename,lastname,suffix','myroles.removed:id','myroles.removed.employee:user_id,firstname,middlename,lastname,suffix')
        ->when($request->keyword, function ($query, $keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%")
                  ->orWhereHas('employee', function ($eq) use ($keyword) {
                      $eq->where('firstname', 'like', "%{$keyword}%")
                         ->orWhere('lastname', 'like', "%{$keyword}%")
                         ->orWhere('middlename', 'like', "%{$keyword}%");
                  });
            });
        })
        ->paginate($request->count);
        return UserResource::collection($data);
    }

    public function status($request){
        $data = User::with('employee:user_id,firstname,middlename,lastname,suffix,avatar,mobile')
        ->with('myroles:role_id,id,user_id','myroles.role:id,name')
        ->where('id',$request->user_id)->first();
        $data->is_active = $request->is_active;
        $data->save();

        return [
            'data' => new UserResource($data),
            'message' => 'User update was successful!', 
            'info' => "You've successfully updated the selected user."
        ];
    }

    public function credential($request){
        $data = User::with('employee')->find($request->user_id);
        $data->email = $request->email;
        if ($request->set) {
            $data->password = bcrypt($request->password);
            $data->must_change = 1;
        }
        if($data->save() && $data->employee){
            $data->employee->mobile = $request->mobile;
            $data->employee->save();
        }
        $data = User::with('employee:user_id,firstname,middlename,lastname,suffix,avatar,mobile')
        ->with('myroles:role_id,id,user_id','myroles.role:id,name')
        ->where('id',$request->user_id)->first();
        return [
            'data' => new UserResource($data),
            'message' => 'User update was successful!', 
            'info' => "You've successfully updated the selected user."
        ];
    }

    public function resetPassword($request){
        $data = User::find($request->id);
        $data->password = bcrypt(self::DEFAULT_PASSWORD);
        $data->must_change = 1;
        $data->save();

        return [
            'data' => new UserResource($data),
            'message' => 'Password reset to default!',
            'info' => "Default password: " . self::DEFAULT_PASSWORD . ". The user will be required to set a new username and password on their next login.",
            'password' => self::DEFAULT_PASSWORD,
        ];
    }

    public function deactivate($request){
        $data = User::find($request->id);
        $data->is_active = !$data->is_active;
        $data->save();

        return [
            'data' => new UserResource($data),
            'message' => $data->is_active ? 'User activated successfully!' : 'User deactivated successfully!',
            'info' => "You've successfully updated the selected user."
        ];

        
    }

    public function user_role($role_request){
        if($role_request->type == 'remove'){
            $data = UserRole::where('role_id', $role_request->role_id)
                ->where('user_id', $role_request->user_id)
                ->firstOrFail();
            $data->removed_by_id = \Auth::user()->id;
            $data->removed_at = now();
            $data->is_active = 0;
            $data->save();
            $id = $data->id;
            $message = 'User role remove was successful!';
        }
        else if($role_request->type == 'set_role_active'){
            $data = UserRole::where('role_id', $role_request->role_id)
                ->where('user_id', $role_request->user_id)
                ->firstOrFail();
            $data->is_active = 1;
            $data->removed_by_id = null;
            $data->removed_at = null;
            $data->update();
            $id = $data->id;
            $message = 'User role set to active was successful!';
        }
        else{
            $data = UserRole::firstOrNew([
                'role_id' => $role_request->role_id,
                'user_id' => $role_request->user_id,
            ]);

            $data->added_by_id = \Auth::user()->id;
            $data->is_active = 1;
            $data->removed_by_id = null;
            $data->removed_at = null;
            $data->save();
            $id = $data->id;
            $message = 'User role added successful!';
        }

        $data = UserRole::findOrFail($id);

        return [
            'data' => new RoleResource($data),
            'message' => $message,
            'info' => "You've successfully updated the selected user."
        ];
    }
}
