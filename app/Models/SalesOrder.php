<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'so_number',
        'order_date',
        'customer_id',
        'status_id',
        'sub_status_id',
        'total_amount',
        'total_discount',
        'added_by_id',
        'updated_by_id',
        'transferred_to',
        'transferred_at',
        'approved_by_id',
        'approved_at',
        'sales_rep_id',
        'driver_id',
        'payment_mode',
        'payment_lines',
        'due_date',
        'shipping_date',
        'delivery_date',
        'delivered_at',
        'delivered_by_id',
        'location_id',
        'delivery_location',
        'cancellation_remarks',
        'requires_batch_approval',
    ];

    protected $casts = [
        'order_date' => 'date',
        'due_date' => 'date',
        'shipping_date' => 'date',
        'delivery_date' => 'date',
        'delivered_at' => 'datetime',
        'transferred_at' => 'date',
        'approved_at' => 'date',
        'total_amount' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'payment_lines' => 'array',
    ];

    /**
     * Modes where the goods leave before the money arrives, so the sale is a
     * receivable and the order stays open until someone collects. COD belongs
     * here: booking it as cash would record collected money and close the order
     * before the driver has left.
     */
    public const ON_ACCOUNT_MODES = ['credit', 'credit sales', 'cod'];

    /**
     * Credit the business actually extends — a term granted to the customer.
     * COD is not this: payment happens at the handover, so it needs no
     * supervisor and no credit limit.
     */
    public const TERM_CREDIT_MODES = ['credit', 'credit sales'];

    public static function isOnAccount(?string $paymentMode): bool
    {
        return in_array(strtolower(trim((string) $paymentMode)), self::ON_ACCOUNT_MODES, true);
    }

    public static function isTermCredit(?string $paymentMode): bool
    {
        return in_array(strtolower(trim((string) $paymentMode)), self::TERM_CREDIT_MODES, true);
    }

    public static function isCod(?string $paymentMode): bool
    {
        return strtolower(trim((string) $paymentMode)) === 'cod';
    }

    /**
     * Goods promised to travel: someone to carry them, or a day to arrive.
     * Everything else is a counter sale the customer carries out themselves.
     */
    public function isDelivery(): bool
    {
        return $this->driver_id !== null || $this->delivery_date !== null;
    }

    /**
     * Handing sacks across the counter is a delivery too — it just happens as
     * the customer pays, so nobody records it separately. Stamping it here is
     * what keeps "closed means delivered" true without taxing every walk-in.
     */
    public function stampCounterDelivery(): void
    {
        if ($this->delivered_at || $this->isDelivery()) {
            return;
        }

        $this->forceFill([
            'delivered_at' => now(),
            'delivered_by_id' => auth()->id(),
        ])->save();
    }

    /**
     * Whether the money a driver collected has reached the office.
     *
     * Only a collection taken in the field is ever out: a counter sale or a
     * payment made at the office is in the moment it is recorded. A field
     * collection stays out until the driver hands it over, which moves the
     * receipt to somebody else.
     */
    public function moneyIsIn(): bool
    {
        if (! $this->driver_id) {
            return true;
        }

        return ! Receipt::whereIn('ar_invoice_id', $this->arInvoices()->select('id'))
            ->where('receipt_type', 'payment')
            ->where('held_by_employee_id', $this->driver_id)
            ->whereNull('remittance_id')
            ->exists();
    }

    /**
     * Where a fully paid order lands.
     *
     * Three things have to be true before the business is finished with an
     * order: the money is recorded, the sacks have arrived, and the money has
     * actually reached the office. Paid but not delivered is For Release.
     * Delivered and paid, with the cash still in the driver's pocket, is For
     * Turnover — Closed would say the business has money it has not seen.
     */
    public function settledStatusSlug(): string
    {
        if (! $this->delivered_at) {
            return 'for-release';
        }

        return $this->moneyIsIn() ? 'closed' : 'for-turnover';
    }

    /**
     * The status id to move a settled order to, or null when that status row is
     * missing — better to leave an order where it is than to blank its status.
     */
    public function settledStatusId(): ?int
    {
        return ListStatus::getBySlug($this->settledStatusSlug())?->id;
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function status()
    {
        return $this->belongsTo(ListStatus::class);
    }

    public function sub_status()
    {
        return $this->belongsTo(ListStatus::class, 'sub_status_id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function approved_by()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function transferredTo()
    {
        return $this->belongsTo(User::class, 'transferred_to');
    }

    public function salesRep()
    {
        return $this->belongsTo(Employee::class, 'sales_rep_id');
    }

    public function driver()
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function location()
    {
        return $this->belongsTo(ListLocation::class, 'location_id');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function arInvoices()
    {
        return $this->hasMany(ArInvoice::class);
    }

    public function deliveryRefusals()
    {
        return $this->hasMany(SalesOrderDeliveryRefusal::class);
    }

    public function returnReplacements()
    {
        return $this->hasMany(\App\Models\SalesReturnReplacement::class);
    }

    public static function generateSoNumber($date = null, $prefix = 'SO')
    {
        $date = $date ?: now();
        $year = $date->format('Y');
        $month = $date->format('m');
        $period = $year . $month;

        $lastSo = self::where('so_number', 'LIKE', $prefix . '-' . $period . '-%')
                      ->orderBy('id', 'desc')
                      ->first();

        $sequence = $lastSo ? intval(substr($lastSo->so_number, -4)) + 1 : 1;

        return $prefix . '-' . $period . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
