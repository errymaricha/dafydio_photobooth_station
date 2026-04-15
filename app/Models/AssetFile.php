<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AssetFile extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'storage_disk',
        'file_path',
        'file_name',
        'file_ext',
        'mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'width',
        'height',
        'file_category',
        'created_by_type',
        'created_by_id',
    ];
}