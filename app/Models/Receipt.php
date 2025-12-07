public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id', 'id');
    }
=======
    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id', 'id');
    }

    public function voidReceipt()
    {
        return $this->hasOne(VoidReceipt::class, 'receipt_id', 'id');
