<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $fillable = [
        'oid',
        'product_id',
        'vid',   
        'quantity',
        'rate_at_confirmation',
        'gerant_rate_at_confirmation',
    ];
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'vid', 'id');
    }
}         