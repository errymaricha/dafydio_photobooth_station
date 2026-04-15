<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintOrder extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_code',
        'session_id',
        'user_id',
        'station_id',
        'printer_id',
        'source_type',
        'order_type',
        'payment_status',
        'total_items',
        'total_qty',
        'subtotal_amount',
        'discount_amount',
        'total_amount',
        'status',
        'ordered_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'ordered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'session_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrintOrderItem::class, 'print_order_id');
    }

    public function queueJobs(): HasMany
    {
        return $this->hasMany(PrintQueueJob::class, 'print_order_id');
    }
}
