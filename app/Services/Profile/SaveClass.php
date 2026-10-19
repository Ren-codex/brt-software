<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SaveClass
{
    public function save($request){
        $request->validate([
            'image' => 'required|image|max:2048' // Assuming maximum file size is 2MB
        ]);

        $user = \Auth::user();
        if ($user->employee->avatar) {
            Storage::disk('public')->delete($user->employee->avatar);
        }

        $imagePath = $request->file('image')->store('employee-pictures', 'public');
        $user->employee->avatar = $imagePath;
        $user->employee->save();

        return [
            'data' => [],
            'message' => 'Employee picture updated successfully.', 
            'info' => "The user's Employee image has been changed to the new photo."
        ];
    }

    public function update($request){
        $data = User::find(\Auth::user()->id);
        $employee = $data->employee;
        if (!$employee) {
            throw ValidationException::withMessages([
                'firstname' => 'This account has no employee record to update.',
            ]);
        }

        // Email is read-only on the profile page, so it is not written here.
        // Name, sex and mobile can't be null in the table; an administrator may
        // leave them blank (ProfileRequest), which keeps the value on file.
        $employee->firstname = $request->firstname ?? $employee->firstname;
        $employee->middlename = $request->middlename;
        $employee->lastname = $request->lastname ?? $employee->lastname;
        $employee->suffix = $request->suffix;
        $employee->sex = $request->gender ?? $employee->sex;
        $employee->mobile = $request->mobile ?? $employee->mobile;
        $employee->save();

        return [
            'data' => new UserResource($data),
            'message' => 'Personal information updated successfully.',
            'info' => "All relevant fields have been refreshed with the latest data."
        ];
    }

    public function destroy($request)
    {
        if (!Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }
        $this->deleteOtherSessionRecords($request);
        return back(303);
    }

    protected function deleteOtherSessionRecords(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return;
        }
        \DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}
