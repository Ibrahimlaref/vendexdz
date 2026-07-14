<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SheetSyncLog extends Model
{
    protected $fillable = [
        'store_id',
        'sheet_id',
        'status',
        'row_number',
        'row_data',
        'error_message',
        'matched_field',
        'order_id',
        'row_hash',
        'order_reference',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function scopeNeedsReview($query)
    {
        return $query->where('status', 'needs_review')->whereNull('resolved_at');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
