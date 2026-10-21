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
            Employee::with(['user.roles', 'user.myroles.role', 'position', 'extraPositions', 'added_by', 'loans.payments'])
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
            'added_by_id' => $userId ?: auth()->id(),
        ]);

        // The other jobs this person covers. Never the paying one — that is
        // position_id above, and payroll reads only that.
        $data->syncExtraPositions($request->input('extra_position_ids', []));

        // "Create a login account" on the Add Employee form. Made exactly as
        // User Management makes one for an employee: the username from their
        // initials and birth date, the default password to be changed at first
        // sign-in, and the employee linked to it.
        if ($request->boolean('create_account')) {
            $this->userClass->save(new \Illuminate\Http\Request([
                'employee_id' => $data->id,
                // users.email cannot be null. Blank is safe: they sign in by
                // username, and the email lookup only runs for an address.
                'email' => $request->email ?? '',
                'role_ids' => is_array($request->role_ids) ? $request->role_ids : [],
            ]));
            $data->refresh();
        }

        $account = $data->load('user')->user;

        return [
            'data' => new EmployeeResource($data->load('extraPositions')),
            'message' => 'Employee saved successfully!',
            'info' => $account
                ? "Account created. Username: {$account->username} — Default password: ".UserClass::DEFAULT_PASSWORD.'. They will be asked to set a new password the first time they sign in.'
                : "You've successfully saved the employee",
            'password' => $account ? UserClass::DEFAULT_PASSWORD : null,
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
            // $user is the account the username path above worked on, if it ran.
            'user_id' => $accountId = $this->linkedAccountId($request, $request->filled('username') ? $user : null, $data),
        ]);

        // Only when the form sent them: other screens update an employee
        // without ever showing this field, and a missing key must not be read
        // as "they cover nothing else now".
        if ($request->has('extra_position_ids')) {
            $data->syncExtraPositions($request->input('extra_position_ids', []));
        }

        $info = "You've successfully updated the employee";
        if ($data->wasChanged('user_id')) {
            $account = $accountId ? User::find($accountId) : null;
            $info = $account
                ? "Linked to the account {$account->username}. It now shows this employee's name."
                : 'Unlinked from its login account.';
        }

        return [
            'data' => new EmployeeResource($data->load('user', 'extraPositions')),
            'message' => 'Employee updated successfully!',
            'info' => $info,
        ];
    }

    /**
     * Which account the employee ends up linked to: the one the username path
     * worked on, else the one picked in the Login Account picker on Edit
     * Employee (null to unlink), else whatever it already had.
     */
    protected function linkedAccountId($request, ?User $fromUsername, Employee $employee): ?int
    {
        if ($fromUsername) {
            return $fromUsername->id;
        }

        if ($request->has('account_id')) {
            return $request->account_id ?: null;
        }

        return $employee->user_id;
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
