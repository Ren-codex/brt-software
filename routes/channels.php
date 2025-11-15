<?php

use Hashids\Hashids;
use App\Models\User;
use App\Models\ChatConversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('system-maintenance', function () {
    return true;
});


Broadcast::channel('chat-room-{id}', function ($user, $id) {
    // $hashids = new Hashids('krad',10);
    // $id = $hashids->decode($id);
    // $conversation = ChatConversation::findOrFail($id[0]);
    // if(!$conversation->is_group){
    //     if($conversation->sender_id == $user->id || $conversation->receivable_id == $user->id){
    //         return $user;
    //     }
    // }else{
    //    return $user;
    // }
    // return false;
    return true;
});

Broadcast::channel('user-room-{id}', function ($user, $id) {
    // return (int) $user->id === (int) $id;
    return true;
});

Broadcast::channel('online', function ($user) {
    $hashids = new Hashids('krad',10);
    $id = $hashids->encode($user->id);
    $user = User::with('profile:id,user_id,firstname,lastname')->where('id',$user->id)->first();
    return ['id' => $id, 'name' => $user->profile->firstname.' '.$user->profile->lastname];
});