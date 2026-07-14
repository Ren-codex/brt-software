<?php

namespace App\Http\Controllers\Libraries;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Libraries\FundClass;
use App\Http\Requests\Libraries\FundRequest;

class FundController extends Controller
{
    use HandlesTransaction;

    public function __construct(protected FundClass $fund) {}

    public function index(Request $request)
    {
        if ($request->option === 'lists') {
            return $this->fund->lists($request);
        }

        return inertia('Modules/Accounting/Funds', [
            'bankAccounts' => BankAccount::active()->orderBy('bank_name')->get(['id', 'bank_name', 'account_name']),
        ]);
    }

    public function store(FundRequest $request)
    {
        $result = $this->handleTransaction(fn() => $this->fund->save($request, auth()->id()));

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ]);
    }

    public function update(FundRequest $request, $id)
    {
        $request->merge(['id' => $id]);
        $result = $this->handleTransaction(fn() => $this->fund->update($request));

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ]);
    }

    public function topUp(Request $request, $id)
    {
        $request->validate([
            'amount'          => 'required|numeric|min:0.01',
            'date'            => 'required|date',
            'description'     => 'nullable|string|max:500',
            'source_type'     => 'nullable|in:cash,bank',
            'bank_account_id' => 'nullable|required_if:source_type,bank|exists:bank_accounts,id',
        ]);

        $result = $this->handleTransaction(fn() => $this->fund->topUp($id, $request));

        if (!$result['status']) {
            return response()->json([
                'message' => $result['info'] ?? $result['message'],
                'info'    => $result['info'],
                'errors'  => $result['errors'],
                'status'  => 'error',
            ], $result['errors'] ? 422 : 500);
        }

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'],
        ]);
    }

    public function adjustBalance(Request $request, $id)
    {
        $request->validate([
            'balance' => 'required|numeric|min:0',
            'reason'  => 'required|string|max:500',
        ]);

        $result = $this->handleTransaction(fn() => $this->fund->adjustBalance($id, $request));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'],
        ]);
    }

    public function toggleActive(Request $request, $id)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $result = $this->handleTransaction(fn() => $this->fund->toggleActive($id, (bool) $request->is_active));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'],
        ]);
    }
}
