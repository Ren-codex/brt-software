<?php

namespace App\Http\Controllers;

use App\Services\DropdownClass;
use App\Services\PrintClass;
use App\Traits\HandlesTransaction;
use Illuminate\Http\Request;
use App\Services\Modules\RemittanceClass;
use App\Http\Requests\Modules\RemittanceRequest;

class RemittanceController extends Controller
{
    use HandlesTransaction;

    public $remittance,$dropdown,$print;

    public function __construct(RemittanceClass $remittance, DropdownClass $dropdown, PrintClass $print){
        $this->dropdown = $dropdown;
        $this->remittance = $remittance;
        $this->print = $print;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->remittance->lists($request);
            break;
            default:
                return inertia('Modules/Sales/Components/Remittances/Index'); 
            break;
        }   
    }

    public function store(RemittanceRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->remittance->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function destroy($id){
        $result = $this->handleTransaction(function () use ($id) {
            return $this->remittance->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function approve(Request $request, $id){
        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->remittance->approve($request, $id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function show($id, Request $request)
    {
        return $this->print->print($id, $request);
    }
}
