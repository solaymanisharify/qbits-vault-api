<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $fillable = ['order_id,customer_name,customer_phone,total_cash_to_deposit,total,payment_status,order_status,status,payable_amount,paid_amount,'];
}
