<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionPhoto extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'session_id',
        'capture_index',
        'slot_index',
        'original_file_id',
        'thumbnail_file_id',
        'composed_preview_file_id',
        'checksum_sha256',
        'width',
        'height',
        'file_size_bytes',
        'mime_type',
        'is_selected',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'uploaded_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'session_id');
    }

    public function originalFile(): BelongsTo
    {
        return $this->belongsTo(AssetFile::class, 'original_file_id');
    }

    public function thumbnailFile(): BelongsTo
    {
        return $this->belongsTo(AssetFile::class, 'thumbnail_file_id');
    }

    public function composedPreviewFile(): BelongsTo
    {
        return $this->belongsTo(AssetFile::class, 'composed_preview_file_id');
    }
}