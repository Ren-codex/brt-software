<?php

namespace App\Services\Libraries;

use App\Http\Resources\Libraries\ListPayrollItemResource;
use App\Models\ListPayrollItem;
use Illuminate\Support\Str;

class PayrollItemClass
{
    public function lists($request)
    {
        return ListPayrollItemResource::collection(
            ListPayrollItem::query()
                ->when($request->keyword, function ($query, $keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                        ->orWhere('slug', 'LIKE', "%{$keyword}%")
                        ->orWhere('description', 'LIKE', "%{$keyword}%")
                        ->orWhere('type', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count ?? 10)
        );
    }

    public function save($request)
    {
        $name = Str::ucfirst(trim((string) $request->name));

        $data = ListPayrollItem::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $request->description,
            'type' => $request->type,
        ]);

        return [
            'data' => new ListPayrollItemResource($data),
            'message' => 'Payroll item saved successfully!',
            'info' => "You've successfully saved the payroll item",
        ];
    }

    public function update($request, $id)
    {
        $data = ListPayrollItem::findOrFail($id);
        $name = Str::ucfirst(trim((string) $request->name));

        $data->update([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $request->description,
            'type' => $request->type,
        ]);

        return [
            'data' => new ListPayrollItemResource($data),
            'message' => 'Payroll item updated successfully!',
            'info' => "You've successfully updated the payroll item",
        ];
    }

    public function delete($id)
    {
        $data = ListPayrollItem::findOrFail($id);
        $data->delete();

        return [
            'data' => $data,
            'message' => 'Payroll item deleted successfully!',
            'info' => "You've successfully deleted the payroll item",
        ];
    }
}
