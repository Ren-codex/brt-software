<?php

namespace App\Http\Controllers\Libraries;

use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\ReceiptClass;
use App\Http\Requests\Libraries\ReceiptRequest;

class ReceiptController extends Controller
{
    use HandlesTransaction;

    public $receipt,$dropdown;

    public function __construct(ReceiptClass $receipt, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->receipt = $receipt;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->receipt->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Receipts/Index'); 
            break;
        }   
    }
}
