<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'station_id',
        'printer_code',
        'printer_name',
        'printer_type',
        'connection_type',
        'ip_address',
        'port',
        'driver_name',
        'paper_size_default',
        'is_default',
        'status',
        'last_seen_at',
        'last_error',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'last_seen_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function queueJobs(): HasMany
    {
        return $this->hasMany(PrintQueueJob::class, 'printer_id');
    }
}