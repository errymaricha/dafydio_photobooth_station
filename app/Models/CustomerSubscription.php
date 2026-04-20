<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSubscription extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'package_id',
        'status',
        'start_at',
        'end_at',
        'auto_renew',
        'upgraded_from_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function upgradedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'upgraded_from_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
