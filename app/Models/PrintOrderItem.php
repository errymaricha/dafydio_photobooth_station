<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintOrderItem extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'print_order_id',
        'rendered_output_id',
        'session_photo_id',
        'file_id',
        'paper_size',
        'copies',
        'print_layout',
        'unit_price',
        'line_total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function printOrder(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class, 'print_order_id');
    }

    public function renderedOutput(): BelongsTo
    {
        return $this->belongsTo(RenderedOutput::class, 'rendered_output_id');
    }
}