<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Dtr;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\Hr\TimeResource;
use Illuminate\Http\Request;
use App\Traits\HandlesTransaction;

class AttendanceController extends Controller
{
    use HandlesTransaction;

    public function index(){
        return inertia('Public/Dtr/Face');
    }

    public function store(Request $request){
        $result = $this->handleTransaction(function () use ($request) {
            $date = Carbon::now();
            $time = Carbon::now();
            $type = $request->type;
            $minutes = 0;
            $is_completed = 0;
            switch($type){
                case 'Time In (am)':
                    if ($date->isMonday()) {
                        $officialStart = Carbon::createFromTimeString('08:00:00');
                        $officialMorningTimeIn = Carbon::createFromTimeString('8:00:59');
                        $minutes = ($time->greaterThan($officialMorningTimeIn)) ? (int)  $officialStart->diffInMinutes($time) : 0;
                    }else{
                        $officialStart = Carbon::createFromTimeString('08:00:00');
                        $flexibleCutoff = Carbon::createFromTimeString('08:30:59');
                        $minutes = ($time->greaterThan($flexibleCutoff)) ? (int) $officialStart->diffInMinutes($time) : 0;
                    }
                break;
                case 'Time Out (am)':
                    $officialMorningOut = Carbon::createFromTimeString('12:00:00');
                    $minutes = ($time->lessThan($officialMorningOut)) ? ceil($time->diffInMinutes($officialMorningOut)) : 0;
                break;
                case 'Time In (pm)':
                    $officialAfternoonTimeIn = Carbon::createFromTimeString('13:00:59');
                    $minutes = ($time->greaterThan($officialAfternoonTimeIn)) ? (int) $officialAfternoonTimeIn->diffInMinutes($time) : 0;
                break;
                case 'Time Out (pm)':
                    $officialAfternoonOut = Carbon::createFromTimeString('17:00:00');
                    $minutes = ($time->lessThan($officialAfternoonOut)) ? ceil($time->floatDiffInMinutes($officialAfternoonOut)) : 0;
                break;
            }
            
            $info = [
                'ip' => \Request::ip(), 
                'pcname' => gethostname(),
                'browser' => $request->header('User-Agent'),
                'time' =>  $time->toTimeString(),
                'date' => $date,
                'minutes' => $minutes,
                'image' => $this->image($request),
                'is_updated' => false,
                'changes' => []
            ];
            $user = User::with('profile','organization.division')->where('username',$request->username)->first();
        
            if($user){
                $dtr = Dtr::whereDate('date',$date)->where('user_id',$user->id)->first();
                $status = null;
                $remarks = [
                    'tardiness' => null,
                    'undertime' => null
                ]; 
                if($dtr){
                    switch($type){
                        case 'Time In (am)':
                            if($date->hour >= 12){
                                $status = 'Disabled';
                            }else if($dtr->am_in_at){
                                $status = 'Duplicate';
                            }else{
                                $status = 'Success';
                                $dtr->am_in_at = json_encode($info);
                                $dtr->save();
                            }
                        break;
                        case 'Time Out (am)':
                            if($dtr->am_out_at){
                                $status = 'Duplicate';
                            }else{
                                $status = 'Success';
                                $dtr->am_out_at = json_encode($info);
                                $dtr->save();
                            }
                        break;
                        case 'Time In (pm)':
                            if (!empty(json_decode($dtr->pm_out_at))) {
                                $status = "Disabled";
                            }else if($date->hour >= 17) {
                                $status = "Disabled2";
                            }else if($dtr->pm_in_at){
                                $status = 'Duplicate';
                            }else{
                                $status = 'Success';
                                $dtr->pm_in_at = json_encode($info);
                                $dtr->save();
                            }
                        break;
                        case 'Time Out (pm)':
                            if(!empty(json_decode($dtr->pm_out_at))){
                                $status = 'Duplicate';
                            }else{
                                $status = 'Success';
                                $dtr->pm_out_at = json_encode($info);
                                $dtr->save();
                            }
                        break;
                    }
                }else{
                    $dtr = new Dtr;
                    $dtr->date = Carbon::today();
                    $dtr->am_in_at = ($type == 'Time In (am)') ? json_encode($info) : null;
                    $dtr->am_out_at = ($type == 'Time Out (am)') ? json_encode($info) : null;
                    $dtr->pm_in_at = ($type == 'Time In (pm)') ? json_encode($info) : null;
                    $dtr->pm_out_at = ($type == 'Time Out (pm)') ? json_encode($info) : null;
                    $dtr->tardiness += $minutes;
                    $dtr->remarks = json_encode($remarks);
                    $dtr->user_id = $user->id;
                    if($dtr->save()){
                        $status = 'New';
                    }
                }
                $name = $user->profile->firstname.' '.$user->profile->lastname;
                $data = [
                    'username' => $user->username,
                    'name' => $name,
                    'division' => $user->organization->division->name,
                    'avatar' => ($user->profile->avatar === 'avatar.jpg') ? '/images/avatars/'.$user->profile->avatar : '/storage/'.$user->profile->avatar,
                    'time' => $time,
                    'type' => $type,
                    'status' => $status,
                    'message' => $this->messages($type,$status,$name)
                ];
                return [
                    'data' => $data,
                    'message' => null, 
                    'info' => $status,
                ];
            }else{
                return [
                    'data' => null,
                    'message' => null, 
                    'info' => 'Error',
                ];
            }
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    private function messages($type,$status,$name){
        switch($type){
            case 'Time In (am)':
                switch($status){
                    case 'Success':
                    case 'New':
                        $message = 'Good morning, <b>'.$name.'</b>! Your time-in (AM) has been recorded successfully. Have a productive day!';
                        $color = 'text-success';
                        $bg = 'bg-success';
                        $a = 'success';
                    break;
                    case 'Duplicate':
                        $message = 'Hello <b>'.$name.'</b>, You have already clocked in for the morning. No duplicate entries allowed.';
                        $color = 'text-warning';
                        $bg = 'bg-warning';
                        $a = 'warning';
                    break;
                    case 'Disabled':
                        $message = 'Time-in (AM) is only available before 12:00 PM, <b>'.$name.'</b>. Please use Time-in (PM) instead.';
                        $color = 'text-danger';
                        $bg = 'bg-danger';
                        $a= 'danger';
                    break;
                }
            break;
            case 'Time Out (am)':
                switch($status){
                    case 'Success':
                    case 'New':
                        $message = 'Hello <b>'.$name.'</b>, Your morning time-out has been recorded. Enjoy your break!';
                        $color = 'text-success';
                        $bg = 'bg-success';
                        $a = 'success';
                    break;
                    case 'Duplicate':
                        $message = 'Hello <b>'.$name.'</b>, You have already clocked out for the morning. No duplicate entries allowed.';
                        $color = 'text-warning';
                        $bg = 'bg-warning';
                        $a = 'warning';
                    break;
                }
            break;
            case 'Time In (pm)':
                switch($status){
                    case 'Success':
                    case 'New':
                        $message = 'Welcome back, <b>'.$name.'</b>! Your afternoon time-in has been recorded successfully.';
                        $color = 'text-success';
                        $bg = 'bg-success';
                        $a = 'success';
                    break;
                    case 'Duplicate':
                        $message = 'Hello <b>'.$name.'</b>, You have already clocked in for the afternoon. No duplicate entries allowed.';
                        $color = 'text-warning';
                        $bg = 'bg-warning';
                        $a = 'warning';
                    break;
                    case 'Disabled':
                        $message = 'Hello, <b>'.$name.'</b>, You have already timed out for today. Time-in (PM) is no longer allowed.';
                        $color = 'text-danger';
                        $bg = 'bg-danger';
                        $a= 'danger';
                    break;
                    case 'Disabled2':
                        $message = 'Hello, <b>'.$name.'</b>, Time-in (PM) is only allowed before 5:00 PM.';
                        $color = 'text-danger';
                        $bg = 'bg-danger';
                        $a= 'danger';
                    break;
                }
            break;
            case 'Time Out (pm)':
                switch($status){
                    case 'Success':
                    case 'New':
                        $message = 'Hello <b>'.$name.'</b>, Your time-out (PM) has been recorded. Have a great rest of the day!';
                        $color = 'text-success';
                        $bg = 'bg-success';
                        $a = 'success';
                    break;
                    case 'Duplicate':
                        $message = 'Hello <b>'.$name.'</b>, You have already clocked out for the day. No duplicate entries allowed.';
                        $color = 'text-warning';
                        $bg = 'bg-warning';
                        $a = 'warning';
                    break;
                }
            break;
        }
        return [
            'message' => $message,
            'color' => $color,
            'bg' => $bg,
            'status' => $a
        ];
    }

    public function image($request)
    {
        $image = $request->input('image'); // base64 string

        // Validate format
        if (!preg_match('/^data:image\/(\w+);base64,/', $image, $matches)) {
            return response()->json(['error' => 'Invalid image format.'], 422);
        }

        $type = strtolower($matches[1]); // png, jpg, jpeg, gif
        if (!in_array($type, ['jpg', 'jpeg', 'png'])) {
            return response()->json(['error' => 'Invalid image type.'], 422);
        }

        // Remove header and decode
        $image = substr($image, strpos($image, ',') + 1);
        $image = str_replace(' ', '+', $image);
        $imageData = base64_decode($image);

        if ($imageData === false) {
            return response()->json(['error' => 'Base64 decode failed.'], 422);
        }

        // Save to storage/app/public/images/attendance
        $filename = Str::random(10) . '.' . $type;
        $path = 'images/attendance/'.$request->username.'/'. $filename;
        Storage::disk('public')->put($path, $imageData);

        return $path;
    }
}
