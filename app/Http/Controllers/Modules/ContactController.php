<?php

namespace App\Http\Controllers\Modules;

use App\Models\Contact;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Modules\ContactClass;

class ContactController extends Controller
{
    public $contact;

    public function __construct(ContactClass $contact){
        $this->contact = $contact;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->contact->lists($request);
            default:
                return inertia('Modules/Contacts/Index');
        }
    }

    public function store(Request $request){
        $result = $this->contact->store($request);

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function markAsRead($id){
        $result = $this->contact->markAsRead($id);

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'status' => $result['status'],
        ]);
    }

    public function destroy($id){
        $result = $this->contact->delete($id);

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
        ]);
    }

    public function getUnreadCount(){
        return response()->json([
            'count' => $this->contact->getUnreadCount(),
        ]);
    }
}
