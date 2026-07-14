<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleSheetConnection extends Model
{
    protected $fillable = [
        'sid',
        'sheet_id',
        'column_mapping',
        'is_active',
        'mapping_confirmed',
        'last_synced_at',
        'sync_interval_minutes',
        'last_synced_row',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'is_active' => 'boolean',
            'mapping_confirmed' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'sid', 'id');
    }

    public function syncLogs()
    {
        return $this->hasMany(SheetSyncLog::class, 'sheet_id', 'sheet_id')
            ->where('store_id', $this->sid);
    }
}
