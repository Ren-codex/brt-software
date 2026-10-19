<?php

namespace App\Services\Modules;

use App\Models\Employee;
use App\Models\ListPosition;
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
                              $q->whereRaw('LOWER(title) LIKE ?', ["%{$keyword}%"]);
                          });
                    });
                })
                ->tap(fn ($query) => $this->applySort($query, $request->sort_by, $request->sort_direction))
                ->paginate($request->count)
        );
        return $data;
    }

    /**
     * Order the employee list. Sorting happens here, not in the browser, so it
     * applies to the whole list rather than to whichever ten rows are on screen.
     *
     * Both inputs are checked against fixed lists: a column name cannot be bound
     * as a query parameter, so anything unrecognised falls back to newest first.
     */
    private function applySort($query, ?string $sortBy, ?string $direction): void
    {
        $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        match ($sortBy) {
            // Matches the name as displayed: first name, then last name.
            'fullname' => $query->orderBy('firstname', $direction)->orderBy('lastname', $direction),
            'email' => $query->orderBy('email', $direction),
            'position' => $query->orderBy(
                ListPosition::select('title')->whereColumn('list_positions.id', 'employees.position_id')->limit(1),
                $direction
            ),
            'created_at' => $query->orderBy('created_at', $direction),
            default => $query->orderBy('created_at', 'desc'),
        };

        // Rows that tie would otherwise be free to swap between pages.
        $query->orderBy('employees.id', $direction);
    }

    public function save($request, $userId = null){
        $avatarPath = 'noavatar.jpg';
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
            'religion' => $request->religion ?? '',
            'address' => $request->address,
            'position_id' => $request->position_id,
            'hours_per_day' => $request->filled('hours_per_day') ? $request->hours_per_day : 8,
            'overtime_rate' => $request->filled('overtime_rate') ? $request->overtime_rate : 0,
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

        $avatarPath = $data->avatar ?: 'noavatar.jpg';
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
            'religion' => $request->religion ?? '',
            'address' => $request->address,
            'position_id' => $request->position_id,
            'hours_per_day' => $request->filled('hours_per_day') ? $request->hours_per_day : 8,
            'overtime_rate' => $request->filled('overtime_rate') ? $request->overtime_rate : 0,
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
