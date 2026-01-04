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

        $data = User::create([
            'username' =>  $request->username,
            'email' =>  $request->email,
            'password' =>  bcrypt($request->password),
            'is_active' => 1,
        ]);

        Employee::create([
            'firstname' =>  $request->firstname,
            'middlename' =>  $request->middlename,
            'lastname' =>  $request->lastname,
            'suffix' =>  $request->suffix,
            'birthdate' =>  $request->birthdate,
            'sex' =>  $request->sex,
            'religion' =>  $request->religion,
            'mobile' =>  $request->mobile,
            'address' =>  $request->address,
            'user_id' =>   $data->id,
        ]);
  
        foreach($request->role_ids as $role_id){   
            UserRole::create([
                'role_id' =>  $role_id,   
                'user_id' =>  $data->id,
                'added_by_id' => \Auth::user()->id,
            ]);
        }

  
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
        ->with('myroles:role_id,id,user_id,added_by_id,removed_by_id,removed_at,created_at,is_active','myroles.role:id,name','myroles.added:id','myroles.added.employee:user_id,firstname,middlename,lastname,suffix','myroles.removed:id','myroles.removed.employee:user_id,firstname,middlename,lastname,suffix')
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
            $data->update();
            $id = $data->id;
            $message = 'User role set to active was successful!';
        }
        else{
            $data = new UserRole;
            $data->role_id = $role_request->role_id;
            $data->user_id = $role_request->user_id;
            $data->added_by_id = \Auth::user()->id;
            $data->is_active = 1;
            $data->save();
            $id = $data->id;
            $message = 'User role add was successful!';
        }

        $data = UserRole::findOrFail($id);

        return [
            'data' => new RoleResource($data),
            'message' => $message,
            'info' => "You've successfully updated the selected user."
        ];
    }
}
