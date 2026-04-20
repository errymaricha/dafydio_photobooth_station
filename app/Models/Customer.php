<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'customer_whatsapp',
        'tier',
        'status',
        'cloud_account_id',
    ];

    protected function customerWhatsapp(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::normalizeWhatsapp($value),
        );
    }

    public function cloudAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerCloudAccount::class, 'cloud_account_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class, 'customer_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class, 'customer_id');
    }

    private static function normalizeWhatsapp(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($input)) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }
}
