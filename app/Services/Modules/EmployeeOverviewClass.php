<?php

namespace App\Services\Modules;

use App\Models\AuthenticationLog;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\System\Permission\PermissionService;
use Illuminate\Support\Carbon;

/**
 * An employee's dealings across the system, for the View Employee modal:
 * their payslips, the sales they made, the deliveries they drove, the cash
 * they are still holding, and their account's sign-ins.
 *
 * Each part is the viewer's to see only if they may see that module, so an
 * Employees viewer without payroll access gets no payroll figures. A part
 * they may not see comes back null and the modal leaves it out.
 */
class EmployeeOverviewClass
{
    public function __construct(protected PermissionService $permissions) {}

    public function for(Employee $employee, User $viewer): array
    {
        $may = fn (string $module, ?string $submodule) => $this->permissions->userHasAccess($viewer, $module, $submodule, 'view');

        return [
            'account' => $employee->user_id && $may('user_management', null) ? $this->account($employee) : null,
            'payroll' => $may('payroll', 'payroll_processing') ? $this->payroll($employee) : null,
            'sales' => $may('sales', 'sales_orders') ? $this->sales($employee) : null,
            'deliveries' => $may('sales', 'sales_orders') ? $this->deliveries($employee) : null,
            'cash' => $may('sales', 'remittances') ? $this->cash($employee) : null,
        ];
    }

    protected function account(Employee $employee): array
    {
        $logs = AuthenticationLog::where('user_id', $employee->user_id);

        return [
            'last_sign_in' => (clone $logs)->where('is_failed', false)->max('created_at'),
            'failed_last_30_days' => (clone $logs)->where('is_failed', true)->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    protected function payroll(Employee $employee): array
    {
        $items = PayrollItem::query()
            ->join('payrolls', 'payrolls.id', '=', 'payroll_items.payroll_id')
            ->where('payroll_items.employee_id', $employee->id)
            ->select('payroll_items.*');

        // Only payrolls that were approved count as pay; a draft or one sent
        // back is not money anybody received.
        $approved = (clone $items)->whereHas('payroll.status', fn ($q) => $q->whereIn('slug', ['for-release', 'completed']));

        return [
            'year_net' => (float) (clone $approved)->whereYear('payrolls.pay_period_end', now()->year)->sum('payroll_items.net_salary'),
            'count' => (clone $items)->count(),
            'recent' => (clone $items)->with('payroll.status')
                ->orderByDesc('payrolls.pay_period_end')->orderByDesc('payroll_items.id')
                ->limit(8)->get()
                ->map(fn (PayrollItem $item) => [
                    'reference' => $item->payroll->payroll_no,
                    'period' => $item->payroll->pay_period_start && $item->payroll->pay_period_end
                        ? $item->payroll->pay_period_start->format('M j').' – '.$item->payroll->pay_period_end->format('M j, Y')
                        : null,
                    'days' => (float) $item->total_days,
                    'net' => (float) $item->net_salary,
                    'status' => $item->payroll->status->name ?? null,
                ])->all(),
        ];
    }

    protected function sales(Employee $employee): array
    {
        $orders = SalesOrder::where('sales_rep_id', $employee->id)
            ->whereDoesntHave('status', fn ($q) => $q->where('slug', 'cancelled'));
        $month = (clone $orders)->whereBetween('order_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);

        return [
            'month_count' => (clone $month)->count(),
            'month_total' => (float) (clone $month)->sum('total_amount'),
            'count' => (clone $orders)->count(),
            'recent' => $this->orderRows((clone $orders)->with(['customer', 'status'])->orderByDesc('order_date')->orderByDesc('id')->limit(8)->get()),
        ];
    }

    protected function deliveries(Employee $employee): array
    {
        $delivered = SalesOrder::where('driver_id', $employee->id)->whereNotNull('delivered_at');

        return [
            'month_count' => (clone $delivered)->where('delivered_at', '>=', now()->startOfMonth())->count(),
            'count' => (clone $delivered)->count(),
            'recent' => $this->orderRows((clone $delivered)->with(['customer', 'status'])->orderByDesc('delivered_at')->limit(8)->get(), 'delivered_at'),
        ];
    }

    /** Money they collected and have not handed over yet. */
    protected function cash(Employee $employee): array
    {
        $held = Receipt::where('held_by_employee_id', $employee->id)
            ->whereNull('remittance_id')
            ->whereHas('status', fn ($q) => $q->where('slug', 'pending'));

        return [
            'count' => (clone $held)->count(),
            'total' => (float) (clone $held)->sum('amount_paid'),
            'recent' => (clone $held)->with('customer')->orderByDesc('receipt_date')->limit(8)->get()
                ->map(fn (Receipt $receipt) => [
                    'reference' => $receipt->receipt_number,
                    'date' => $this->date($receipt->receipt_date),
                    'customer' => $receipt->customer?->name,
                    'mode' => $receipt->payment_mode ?: 'Cash',
                    'amount' => (float) $receipt->amount_paid,
                ])->all(),
        ];
    }

    protected function orderRows($orders, string $dateColumn = 'order_date'): array
    {
        return $orders->map(fn (SalesOrder $order) => [
            'reference' => $order->so_number,
            'date' => $this->date($order->{$dateColumn}),
            'customer' => $order->customer->name ?? 'Walk-in customer',
            'amount' => (float) $order->total_amount,
            'status' => $order->status->name ?? null,
        ])->all();
    }

    protected function date($value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }
}
