<?php

namespace App\Services\Libraries;

use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Http\Resources\Libraries\FundResource;
use Illuminate\Validation\ValidationException;

class FundClass
{
    public function lists($request)
    {
        return FundResource::collection(
            PettyCashFund::when($request->keyword, fn($q, $kw) => $q->where('name', 'LIKE', "%{$kw}%"))
                ->orderBy('name')
                ->paginate($request->count ?? 15)
        );
    }

    public function save($request, $userId = null)
    {
        $data = PettyCashFund::create([
            'name'          => $request->name,
            'gl_code'       => $request->gl_code,
            'balance'       => 0,
            'weekly_budget'         => $request->weekly_budget ?? null,
            'low_balance_threshold' => $request->low_balance_threshold ?? null,
            'is_active'             => true,
            'created_by_id' => $userId ?: auth()->id(),
        ]);

        return [
            'data'    => new FundResource($data),
            'message' => 'Fund created successfully!',
            'info'    => "Petty cash fund '{$data->name}' has been created.",
        ];
    }

    public function update($request)
    {
        $data = PettyCashFund::findOrFail($request->id);
        $data->update([
            'name'                  => $request->name,
            'gl_code'               => $request->gl_code,
            'weekly_budget'         => $request->weekly_budget ?? null,
            'low_balance_threshold' => $request->low_balance_threshold ?? null,
        ]);

        return [
            'data'    => new FundResource($data->fresh()),
            'message' => 'Fund updated successfully!',
            'info'    => "Petty cash fund '{$data->name}' has been updated.",
        ];
    }

    public function topUp($id, $request)
    {
        $amount = (float) $request->amount;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Top-up amount must be greater than zero.',
            ]);
        }

        $fund = PettyCashFund::findOrFail($id);

        PettyCashTransaction::create([
            'fund_id'          => $fund->id,
            'type'             => 'replenishment',
            'amount'           => $amount,
            'transaction_date' => $request->date ?? now()->toDateString(),
            'description'      => $request->description ?? null,
            'transaction_no'   => 'TU-' . strtoupper(uniqid()),
            'created_by_id'    => auth()->id(),
        ]);

        $fund->increment('balance', $amount);

        return [
            'data'    => new FundResource($fund->fresh()),
            'message' => 'Fund topped up successfully!',
            'info'    => "₱" . number_format($amount, 2) . " added to '{$fund->name}'.",
        ];
    }

    public function adjustBalance($id, $request)
    {
        $fund = PettyCashFund::findOrFail($id);
        $fund->update(['balance' => (float) $request->balance]);

        return [
            'data'    => new FundResource($fund->fresh()),
            'message' => 'Balance adjusted successfully!',
            'info'    => "Balance set to ₱" . number_format($fund->balance, 2) . ".",
        ];
    }

    public function toggleActive($id, bool $isActive)
    {
        $fund = PettyCashFund::findOrFail($id);
        $fund->update(['is_active' => $isActive]);

        return [
            'data'    => new FundResource($fund->fresh()),
            'message' => $isActive ? 'Fund activated.' : 'Fund deactivated.',
            'info'    => "Fund '{$fund->name}' is now " . ($isActive ? 'active' : 'inactive') . ".",
        ];
    }
}
