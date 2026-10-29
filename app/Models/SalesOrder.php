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
        'checker_id',
        'payment_mode',
        'payment_lines',
        'due_date',
        'shipping_date',
        'delivery_date',
        'delivered_at',
        'delivered_by_id',
        'closed_at',
        'delivery_submission',
        'delivery_encoded_at',
        'delivery_rejection',
        'delivery_approved_by_id',
        'delivery_approved_at',
        'credit_approval',
        'credit_approval_remarks',
        'credit_approved_by_id',
        'credit_approved_at',
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
        'closed_at' => 'datetime',
        'delivery_submission' => 'array',
        'delivery_encoded_at' => 'datetime',
        'delivery_approved_at' => 'datetime',
        'credit_approved_at' => 'datetime',
        'transferred_at' => 'date',
        'approved_at' => 'date',
        'total_amount' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'payment_lines' => 'array',
    ];

    /**
     * When the business finished with this order.
     *
     * Closing is not an action anybody takes: a dozen places re-derive the
     * status after a payment, a delivery or a handover, and one of them lands
     * on Closed. Stamping it in each of them would mean missing one, so it is
     * stamped here, where every status change passes.
     *
     * Reopening clears it. An order knocked back to For Turnover is not
     * finished, and a date saying it was would outlive the fact.
     */
    protected static function booted(): void
    {
        static::updating(function (self $order) {
            if (! $order->isDirty('status_id')) {
                return;
            }

            $closedId = ListStatus::getBySlug('closed')?->id;
            if (! $closedId) {
                return;
            }

            if ((int) $order->status_id === (int) $closedId) {
                $order->closed_at = now();
            } elseif ($order->closed_at) {
                $order->closed_at = null;
            }
        });
    }

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
        if ($this->delivered_at || $this->isDelivery() || self::isOnAccount($this->payment_mode)) {
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
        // Either of the pair may be carrying it: the checker normally, the
        // driver on an order sent out without one or collected before the
        // checker was named.
        $crew = array_filter([$this->driver_id, $this->checker_id]);

        if (! $crew) {
            return true;
        }

        return ! Receipt::whereIn('ar_invoice_id', $this->arInvoices()->select('id'))
            ->where('receipt_type', 'payment')
            ->whereIn('held_by_employee_id', $crew)
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
     *
     * For Turnover arrived after the original status list was written, and that
     * list is a seeder which deletes the whole table before rebuilding it from
     * fixed ids. It cannot be re-run on a live database, so the row has to come
     * from somewhere else: it is created here on first use rather than waiting
     * for somebody to ssh in and seed it. Same approach as
     * JournalEntryService::ensureAccount, which conjures the ledger accounts a
     * new feature needs instead of requiring a migration for each one.
     */
    public function settledStatusId(): ?int
    {
        $slug = $this->settledStatusSlug();

        if ($slug === 'for-turnover' && ! ListStatus::getBySlug($slug)) {
            ListStatus::create([
                'name' => 'For Turnover',
                'slug' => 'for-turnover',
                'description' => 'Delivered and paid, money not yet handed in',
                'text_color' => '#ffffff',
                'bg_color' => '#b0702a',
            ]);
        }

        return ListStatus::getBySlug($slug)?->id;
    }

    /** Placed on credit, and not yet cleared to go out. */
    public function awaitsCreditApproval(): bool
    {
        return $this->credit_approval === 'pending';
    }

    public function scopeAwaitingCreditApproval($query)
    {
        return $query->where('credit_approval', 'pending')
            ->whereDoesntHave('status', fn ($q) => $q->where('slug', 'cancelled'));
    }

    /**
     * What `credit_approval` should read for an order on these terms.
     *
     * Credit the business extends needs an approver; COD and cash do not. An
     * approval already given stands for as long as the order stays on credit,
     * so editing the remarks on an approved order does not send it round again.
     */
    public static function creditApprovalFor(?string $paymentMode, ?string $current = null): ?string
    {
        if (! config('sales.credit_approval', true) || ! self::isTermCredit($paymentMode)) {
            return null;
        }

        return $current === 'approved' ? 'approved' : 'pending';
    }

    /** Encoded as delivered, and waiting on an approver before it counts. */
    public function awaitsDeliveryApproval(): bool
    {
        return ! $this->delivered_at && ! empty($this->delivery_submission);
    }

    public function scopeAwaitingDeliveryApproval($query)
    {
        return $query->whereNull('delivered_at')
            ->whereNotNull('delivery_submission')
            ->whereDoesntHave('status', fn ($q) => $q->where('slug', 'cancelled'));
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by_id');
    }

    public function creditApprovedBy()
    {
        return $this->belongsTo(User::class, 'credit_approved_by_id');
    }

    public function deliveryApprovedBy()
    {
        return $this->belongsTo(User::class, 'delivery_approved_by_id');
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

    public function checker()
    {
        return $this->belongsTo(Employee::class, 'checker_id');
    }

    /**
     * Who takes the customer's money at the door.
     *
     * The checker when the order has one — that is their job on the truck —
     * otherwise the driver, who does both on a run without a checker. Null on
     * an order nobody is delivering.
     */
    public function fieldCollectorId(): ?int
    {
        return $this->checker_id ?? $this->driver_id;
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
