<?php

namespace App\Services\Modules;

use App\Models\Employee;
use App\Models\User;
use App\Http\Resources\Modules\EmployeeResource;
use App\Services\System\User\UserClass;


class EmployeeClass
{
    protected $userClass;

    public function __construct(UserClass $userClass)
    {
        $this->userClass = $userClass;
    }

    public function lists($request){
        $data = EmployeeResource::collection(
            Employee::with(['user.roles', 'user.myroles.role', 'position', 'added_by', 'loans.payments'])
                ->when($request->keyword, function ($query,$keyword) {
                    $keyword = strtolower($keyword);
                    $query->where(function($q) use ($keyword) {
                        $q->whereRaw('LOWER(firstname) LIKE ?', ["%{$keyword}%"])
                          ->orWhereRaw('LOWER(lastname) LIKE ?', ["%{$keyword}%"])
                          ->orWhereRaw('LOWER(email) LIKE ?', ["%{$keyword}%"])
                          ->orWhereRaw('LOWER(mobile) LIKE ?', ["%{$keyword}%"])
                          ->orWhereRaw('LOWER(address) LIKE ?', ["%{$keyword}%"])
                          ->orWhereHas('user', function($q) use ($keyword) {
                              $q->WhereRaw('LOWER(email) LIKE ?', ["%{$keyword}%"])
                                ->orWhereRaw('LOWER(username) LIKE ?', ["%{$keyword}%"]);
                          })
                          ->orWhereHas('position', function($q) use ($keyword) {
                              $q->whereRaw('LOWER(title) LIKE ?', ["%{$keyword}%"])
                                ->orWhereRaw('LOWER(short) LIKE ?', ["%{$keyword}%"]);
                          });
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request, $userId = null){
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = null;
        if ($request->filled('username') && $request->filled('password')) {
            $roleIds = is_array($request->role_ids) ? $request->role_ids : [];
            $userRequest = new \Illuminate\Http\Request([
                'username' => $request->username,
                'email' => $request->email,
                'password' => $request->password,
                'role_ids' => $roleIds
            ]);
            $userResult = $this->userClass->save($userRequest);
            $user = User::find($userResult['data']['id']);
        }

        $data = Employee::create([
            'firstname' => $request->firstname,
            'middlename' => $request->middlename,
            'lastname' => $request->lastname,
            'suffix' => $request->suffix,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'birthdate' => $request->birthdate,
            'sex' => $request->sex,
            'religion' => $request->religion,
            'address' => $request->address,
            'position_id' => $request->position_id,
            'avatar' => $avatarPath,
            'is_regular' => $request->is_regular,
            'is_active' => $request->is_active,
            'is_blacklisted' => $request->is_blacklisted,
            'user_id' => $user ? $user->id : null,
            'added_by_id' => $userId ?: auth()->id(),
        ]);

        return [
            'data' => new EmployeeResource($data->load('user')),
            'message' => 'Employee saved successfully!',
            'info' => "You've successfully saved the employee"
        ];
    }

    public function update($request){
        $data = Employee::findOrFail($request->id);

        $avatarPath = $data->avatar;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        // Handle user account
        $user = $data->user;
        if ($request->filled('username')) {
            $roleIds = is_array($request->role_ids) ? $request->role_ids : [];
            if ($user) {
                // Update existing user
                $updateRequest = new \Illuminate\Http\Request([
                    'id' => $user->id,
                    'username' => $request->username,
                    'email' => $request->email,
                    'role_ids' => $roleIds
                ]);
                if ($request->filled('password')) {
                    $updateRequest->merge(['password' => $request->password]);
                }
                $this->userClass->update($updateRequest);
            } else {
                // Create new user
                $userRequest = new \Illuminate\Http\Request([
                    'username' => $request->username,
                    'email' => $request->email,
                    'password' => $request->password,
                    'role_ids' => $roleIds
                ]);
                $userResult = $this->userClass->save($userRequest);
                $user = User::find($userResult['data']['id']);
            }
        }

        $data->update([
            'firstname' => $request->firstname,
            'middlename' => $request->middlename,
            'lastname' => $request->lastname,
            'suffix' => $request->suffix,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'birthdate' => $request->birthdate,
            'sex' => $request->sex,
            'religion' => $request->religion,
            'address' => $request->address,
            'position_id' => $request->position_id,
            'avatar' => $avatarPath,
            'is_regular' => $request->is_regular,
            'is_active' => $request->is_active,
            'is_blacklisted' => $request->is_blacklisted,
            'user_id' => $user ? $user->id : $data->user_id,
        ]);

        return [
            'data' => new EmployeeResource($data->load('user')),
            'message' => 'Employee updated successfully!',
            'info' => "You've successfully updated the employee"
        ];
    }

    public function toggleActive($request){
        $data = Employee::findOrFail($request->id);
        $data->update([
            'is_active' => $request->is_active,
        ]);

        return [
            'data' => new EmployeeResource($data),
            'message' => 'Employee status updated successfully!',
            'info' => "You've successfully updated the employee status"
        ];
    }

    public function delete($id){
        $data = Employee::findOrFail($id);
        $data->delete();

        return [
            'data' => null,
            'message' => 'Employee deleted successfully!',
            'info' => "You've successfully deleted the employee"
        ];
    }



}
