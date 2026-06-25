<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\Controller;
use App\Http\Requests\Libraries\PackagingRequest;
use App\Services\Libraries\PackagingClass;
use App\Traits\HandlesTransaction;
use Illuminate\Http\Request;

class PackagingController extends Controller
{
    use HandlesTransaction;

    public $packaging;

    public function __construct(PackagingClass $packaging)
    {
        $this->packaging = $packaging;
    }

    public function index(Request $request)
    {
        switch ($request->option) {
            case 'lists':
                return $this->packaging->lists($request);
            default:
                return inertia('Modules/Libraries/Packagings/Index');
        }
    }

    public function store(PackagingRequest $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->packaging->save($request);
        });

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ]);
    }

    public function update(PackagingRequest $request, $id)
    {
        $request->merge(['id' => $id]);

        $result = $this->handleTransaction(function () use ($request) {
            return $this->packaging->update($request);
        });

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ]);
    }

    public function destroy($id)
    {
        $result = $this->handleTransaction(function () use ($id) {
            return $this->packaging->delete($id);
        });

        return response()->json([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ], $result['status'] ? 200 : 400);
    }
}
