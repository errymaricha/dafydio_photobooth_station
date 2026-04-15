<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'voucher_code',
        'voucher_type',
        'status',
        'valid_from',
        'valid_until',
        'max_usage',
        'used_count',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_purchase_amount',
        'notes',
        'metadata_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'max_usage' => 'integer',
            'used_count' => 'integer',
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'min_purchase_amount' => 'decimal:2',
            'metadata_json' => 'array',
        ];
    }
}
