public function arInvoice()
    {
        return $this->hasOne('App\Models\ArInvoice', 'sales_order_id', 'id');
    }
=======
    public function arInvoice()
    {
        return $this->hasOne('App\Models\ArInvoice', 'sales_order_id', 'id');
    }

    public function salesReturns()
    {
        return $this->hasMany('App\Models\SalesReturn', 'sales_order_id', 'id');
    }
