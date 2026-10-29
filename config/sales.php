<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery approval
    |--------------------------------------------------------------------------
    |
    | When on, "Mark Delivered" only submits a delivery: it waits as pending
    | until someone with the approver level on Sales Orders confirms it, and
    | only then is the delivery — and any money collected at the door —
    | actually recorded. Turn it off to record deliveries the moment they are
    | encoded.
    |
    */

    'delivery_approval' => (bool) env('DELIVERY_APPROVAL', false),

    /*
    |--------------------------------------------------------------------------
    | Credit approval
    |--------------------------------------------------------------------------
    |
    | When on, every sales order placed on credit is saved as waiting for
    | approval. It cannot be delivered or take a payment until someone with
    | the approver level on Sales Orders approves it; rejecting it cancels
    | the order. COD and cash orders are not affected.
    |
    */

    'credit_approval' => (bool) env('CREDIT_APPROVAL', true),

];
