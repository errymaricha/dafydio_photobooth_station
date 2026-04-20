<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'package_code',
        'package_name',
        'description',
        'duration_days',
        'session_quota',
        'print_quota',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function customerSubscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class, 'package_id');
    }
}
