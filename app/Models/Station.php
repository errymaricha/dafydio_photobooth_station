<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'station_code',
        'station_name',
        'location_name',
        'local_ip',
        'public_url',
        'timezone',
        'photobooth_price',
        'additional_print_price',
        'currency_code',
        'status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'photobooth_price' => 'decimal:2',
            'additional_print_price' => 'decimal:2',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(AndroidDevice::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class);
    }

    public function printOrders(): HasMany
    {
        return $this->hasMany(PrintOrder::class);
    }
}
