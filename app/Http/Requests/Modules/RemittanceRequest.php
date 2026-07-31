<?php

namespace App\Http\Requests\Modules;

use Illuminate\Foundation\Http\FormRequest;

class RemittanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'summary' => 'required|array',
            // total_amount is accepted for backwards compatibility but the
            // stored value is recomputed from the receipts in RemittanceClass,
            // so a tampered payload cannot change what is banked.
            'total_amount' => 'required|numeric|min:0',
            'receipts' => 'required|array|min:1',
            'receipts.*' => 'required|integer|distinct|exists:receipts,id',
        ];
    }
}
