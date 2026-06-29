<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    public function update(Request $request, string $key): \Illuminate\Http\JsonResponse
    {
        $request->validate(['value' => 'required']);

        AppSetting::set($key, $request->value);

        return response()->json([
            'status'  => true,
            'message' => 'Setting saved.',
            'key'     => $key,
            'value'   => $request->value,
        ]);
    }
}
