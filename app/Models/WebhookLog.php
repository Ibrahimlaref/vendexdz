<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'sid',
        'store_integration_id',
        'source',
        'platform',
        'event_type',
        'payload',
        'status',
        'signature_valid',
        'processing_result',
        'platform_order_id',
        'response_code',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(User::class, 'sid', 'id');
    }

    public function integration()
    {
        return $this->belongsTo(StoreIntegration::class, 'store_integration_id');
    }
}
