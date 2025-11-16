<?php

namespace App\Http\Requests\System;

use Hashids\Hashids;
use App\Models\UserProfile;
use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->option === 'credential') {
            return [
                'code' => 'required|string',
                'email' => 'required|email',
                'mobile' => 'required|string',
                'kradworkz' => 'nullable|string',
            ];
        }
        return [
            //
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            if ($this->option === 'credential') {
                if (\App\Models\User::where('email', $this->email)->exists()) {
                    $validator->errors()->add('email', 'The email has already been taken.');
                }

                $mobileExists = \App\Models\UserProfile::all()->filter(function ($profile) use ($user) {
                    try {
                        return $profile->mobile 
                            && decrypt($profile->mobile) === $this->mobile
                            && $profile->id !== ($user->profile->id ?? null);
                    } catch (\Exception $e) {
                        return false;
                    }
                })->count();

                if ($mobileExists) {
                    $validator->errors()->add('mobile', 'The mobile number has already been taken.');
                }
            }
        });
    }
}
