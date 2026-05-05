<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetectedPrinter extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'station_id',
        'linked_printer_id',
        'os_identifier',
        'printer_name',
        'printer_type',
        'connection_type',
        'ip_address',
        'port',
        'driver_name',
        'paper_size_default',
        'status',
        'is_default',
        'capabilities_json',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'capabilities_json' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function linkedPrinter(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'linked_printer_id');
    }
}
