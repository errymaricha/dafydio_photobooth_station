<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    //
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
}
