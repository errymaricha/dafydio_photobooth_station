<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintQueueJob extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'print_order_id',
        'printer_id',
        'queue_name',
        'priority',
        'job_payload',
        'attempt_count',
        'max_attempts',
        'status',
        'last_error',
        'queued_at',
        'processed_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'job_payload' => 'array',
            'queued_at' => 'datetime',
            'processed_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function printOrder(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class, 'print_order_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'printer_id');
    }
}