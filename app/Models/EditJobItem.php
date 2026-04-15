<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditJobItem extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'edit_job_id',
        'session_photo_id',
        'slot_index',
        'crop_json',
        'transform_json',
        'filter_json',
    ];

    protected function casts(): array
    {
        return [
            'crop_json' => 'array',
            'transform_json' => 'array',
            'filter_json' => 'array',
        ];
    }

    public function editJob(): BelongsTo
    {
        return $this->belongsTo(EditJob::class, 'edit_job_id');
    }

    public function sessionPhoto(): BelongsTo
    {
        return $this->belongsTo(SessionPhoto::class, 'session_photo_id');
    }
}