<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoSession extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'session_code',
        'station_id',
        'device_id',
        'user_id',
        'subscription_id',
        'template_id',
        'session_type',
        'source_type',
        'total_expected_photos',
        'captured_count',
        'status',
        'payment_status',
        'payment_method',
        'payment_ref',
        'paid_at',
        'captured_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AndroidDevice::class, 'device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SessionPhoto::class, 'session_id')
            ->orderBy('capture_index');
    }

    public function printOrders(): HasMany
    {
        return $this->hasMany(PrintOrder::class, 'session_id');
    }

    public function editJobs(): HasMany
    {
        return $this->hasMany(EditJob::class, 'session_id');
    }

    public function renderedOutputs(): HasMany
    {
        return $this->hasMany(RenderedOutput::class, 'session_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(SessionVoucher::class, 'session_id');
    }
}
