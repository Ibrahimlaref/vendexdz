<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'oid',
        'tracking',
        'cid',
        'sid',
        'aid',
        'app_id',
        'custom_id',
        'from',
        'platform_order_id',
        'store_integration_id',
        'type'
    ];

    protected $apends = ['duplicated'];

    public function getRouteKeyName()
    {
        return 'oid';
    }
    public function items()
    {
        return $this->hasMany(OrderItems::class, 'oid', 'oid');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'aid', 'id');
    }

    public function integration()
    {
        return $this->belongsTo(StoreIntegration::class, 'store_integration_id');
    }
    public function store()
    {
        return $this->belongsTo(Store::class, 'sid', 'id');
    }
    public function Notes()
    {
        return $this->hasMany(OrderNots::class, 'oid', 'oid')->latest();
    }

    public function chats()
    {
        return $this->hasMany(order_Comments::class, 'oid', 'oid');
    }

    public function histories()
    {
        return $this->hasMany(OrderLog::class, 'oid', 'oid')->latest();
    }

    public function latestLog()
    {
        return $this->hasOne(OrderLog::class, 'oid', 'oid')->latestOfMany();
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'cid', 'id');
    }

    public function details()
    {
        return $this->hasOne(OrderDetails::class, 'oid', 'oid');
    }

    public function Inconfirmation()
    {
        return $this->hasOne(OrderInconfirmation::class, 'oid', 'oid');
    }
    public function Postpend()
    {
        return $this->hasOne(OrderInconfirmation::class, 'oid', 'oid');
    }
    public function Waiting()
    {
        return $this->hasOne(OrderWaiting::class, 'oid', 'oid');
    }

    public function Indelivery()
    {
        return $this->hasOne(OrderIndelivery::class, 'oid', 'oid');
    }

    public function IndeliveryNotDone()
    {
        return $this->hasOne(OrderIndelivery::class, 'oid', 'oid')->whereNotIn('ssid', [12, 8, 17]);
    }

    public function Done()
    {
        return $this->hasOne(OrderDone::class, 'oid', 'oid');
    }

    public function Timer()
    {
        return $this->hasOne(Timer::class, 'oid', 'oid');
    }
    public function logs()
    {
        return $this->hasMany(OrderLog::class, 'oid', 'oid')->latest();
    }

    public function getDuplicatedAttribute()
    {
        return self::where('cid', $this->cid)
            ->where('sid', $this->sid)
            ->where('id', '!=', $this->id)
            ->exists();
    }

    public function deliveryCompany()
    {
        return $this->belongsTo(SupportedApps::class, 'app_id', 'app_id');
    }
}
