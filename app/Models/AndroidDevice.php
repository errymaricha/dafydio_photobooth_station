<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AndroidDevice extends Authenticatable
{
    use HasUuids, HasApiTokens;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'station_id',
        'device_code',
        'device_name',
        'api_key_hash',
        'local_ip',
        'app_version',
        'os_version',
        'last_heartbeat_at',
        'battery_percent',
        'status',
    ];

    protected $hidden = [
        'api_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class, 'device_id');
    }
}
