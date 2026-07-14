<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'created_by',
        'store_id',
        'name',
        'nickname',
        'url',
        'category_id',
        'price',
        'gerant_rate',
    ];

    public static $rules = [
        'name' => 'required|string|max:255',
        'category_id' => 'required|exists:categories,id',
        'price' => 'required|numeric|min:0',
        'variants.*.quantity' => 'required|integer|min:0',
        'variants.*.discount' => 'required|integer|min:0',
        'variants.*.sku' => 'required|string',
    ];

    public function variants() {
          return $this->hasMany(ProductVariant::class, 'product_id', 'id');
    }
    public function agents()
    {
        return $this->belongsToMany(User::class, 'product_agents', 'product_id', 'aid', 'id', 'id')
                ->withPivot('portion', 'is_active');
    }

    public function agentAssignments()
    {
        return $this->hasMany(ProductAgent::class, 'product_id', 'id');
    }

    public function store(){
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }
}
