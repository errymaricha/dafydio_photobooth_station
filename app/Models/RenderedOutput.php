<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenderedOutput extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'session_id',
        'edit_job_id',
        'file_id',
        'version_no',
        'render_type',
        'width',
        'height',
        'dpi',
        'is_active',
        'rendered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rendered_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'session_id');
    }

    public function editJob(): BelongsTo
    {
        return $this->belongsTo(EditJob::class, 'edit_job_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(AssetFile::class, 'file_id');
    }
}