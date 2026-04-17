<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomerCloudAccount extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'customer_whatsapp',
        'cloud_username',
        'cloud_password_hash',
        'password_set_at',
        'status',
    ];

    protected $hidden = [
        'cloud_password_hash',
    ];

    protected function casts(): array
    {
        return [
            'password_set_at' => 'datetime',
        ];
    }
}
