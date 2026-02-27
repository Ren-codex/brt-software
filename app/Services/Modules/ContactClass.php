<?php

namespace App\Services\Modules;

use App\Models\Contact;
use App\Http\Resources\Modules\ContactResource;

class ContactClass
{
    public function lists($request){
        $data = ContactResource::collection(
            Contact::when($request->keyword, function ($query, $keyword) {
                $keyword = strtolower($keyword);
                $query->where(function($q) use ($keyword) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"])
                      ->orWhereRaw('LOWER(email) LIKE ?', ["%{$keyword}%"])
                      ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$keyword}%"])
                      ->orWhereRaw('LOWER(message) LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->when($request->unread, function ($query) {
                $query->where('is_read', false);
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($request->count ?? 10)
        );
        return $data;
    }

    public function store($request){
        $data = Contact::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'message' => $request->message,
            'is_read' => false,
        ]);

        return [
            'data' => new ContactResource($data),
            'message' => 'Message sent successfully!',
            'info' => "Thank you for contacting us. We'll get back to you soon.",
            'status' => true,
        ];
    }

    public function markAsRead($id){
        $data = Contact::findOrFail($id);
        $data->update(['is_read' => true]);

        return [
            'data' => new ContactResource($data),
            'message' => 'Contact marked as read!',
            'status' => true,
        ];
    }

    public function delete($id){
        $data = Contact::findOrFail($id);
        $data->delete();

        return [
            'data' => null,
            'message' => 'Contact deleted successfully!',
            'status' => true,
        ];
    }

    public function getUnreadCount(){
        return Contact::where('is_read', false)->count();
    }
}
