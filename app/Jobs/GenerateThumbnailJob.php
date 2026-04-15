<?php

namespace App\Jobs;

use App\Models\AssetFile;
use App\Models\SessionPhoto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class GenerateThumbnailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $sessionPhotoId
    ) {}

    public function handle(): void
    {
        $sessionPhoto = SessionPhoto::with([
            'session.station',
            'originalFile',
        ])->find($this->sessionPhotoId);

        if (!$sessionPhoto || !$sessionPhoto->originalFile || !$sessionPhoto->session || !$sessionPhoto->session->station) {
            return;
        }

        $originalDisk = $sessionPhoto->originalFile->storage_disk;
        $originalPath = $sessionPhoto->originalFile->file_path;

        if (!Storage::disk($originalDisk)->exists($originalPath)) {
            return;
        }

        $imageBinary = Storage::disk($originalDisk)->get($originalPath);

        $manager = new ImageManager(new Driver());
        $image = $manager->read($imageBinary);

        $width = $image->width();
        $height = $image->height();

        $image->scale(width: 400);

        $thumbDir = 'stations/'
            . $sessionPhoto->session->station->station_code
            . '/sessions/'
            . now()->format('Y/m/d')
            . '/'
            . $sessionPhoto->session->session_code
            . '/thumbs';

        $ext = $sessionPhoto->originalFile->file_ext ?: 'jpg';

        $thumbName = 'TH_'
            . str_pad((string) $sessionPhoto->capture_index, 2, '0', STR_PAD_LEFT)
            . '.'
            . $ext;

        $thumbPath = $thumbDir . '/' . $thumbName;

        Storage::disk('public')->put($thumbPath, (string) $image->encode());

        $thumbAsset = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => $thumbPath,
            'file_name' => $thumbName,
            'file_ext' => $ext,
            'mime_type' => $sessionPhoto->originalFile->mime_type,
            'file_size_bytes' => Storage::disk('public')->size($thumbPath),
            'width' => $image->width(),
            'height' => $image->height(),
            'file_category' => 'thumb',
            'created_by_type' => 'system',
            'created_by_id' => null,
        ]);

        $sessionPhoto->update([
            'thumbnail_file_id' => $thumbAsset->id,
            'width' => $width,
            'height' => $height,
        ]);
    }
}