<?php

namespace App\Traits;

trait HandlesImage
{
    public function handleImage($id,$data,$username){
        $img = explode(',', $data);
        $ini =substr($img[0], 11);
        $type = explode(';', $ini);
        if($type[0] == 'png'){
            $image = str_replace('data:image/png;base64,', '', $data);
        }else{
            $image = str_replace('data:image/jpeg;base64,', '', $data);
        }
        $image = str_replace(' ', '+', $image);
        $imageName = $username.'.'.$type[0];

        if(\File::put(public_path('imagess/avatars'). '/' . $imageName, base64_decode($image))){
            $data = User::findOrFail($id);
            $data->avatar = $imageName;
            $data->save();
        }
    }
}
