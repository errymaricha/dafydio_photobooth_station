<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\AssetFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TemplateAssetController extends Controller
{
    public function show(Request $request, AssetFile $assetFile): Response
    {
        if (! in_array($assetFile->file_category, ['template_overlay', 'template_thumbnail'], true)) {
            Log::warning('Template asset rejected by category', [
                'asset_id' => $assetFile->id,
                'file_category' => $assetFile->file_category,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
            abort(404);
        }

        $disk = Storage::disk($assetFile->storage_disk);

        if (! $disk->exists($assetFile->file_path)) {
            Log::warning('Template asset missing on disk', [
                'asset_id' => $assetFile->id,
                'disk' => $assetFile->storage_disk,
                'file_path' => $assetFile->file_path,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
            abort(404);
        }

        $absolutePath = $disk->path($assetFile->file_path);

        if (! is_file($absolutePath)) {
            Log::warning('Template asset absolute file missing', [
                'asset_id' => $assetFile->id,
                'absolute_path' => $absolutePath,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
            abort(404);
        }

        $expectedSize = filesize($absolutePath);
        if (! is_int($expectedSize) || $expectedSize <= 0) {
            Log::warning('Template asset has invalid file size', [
                'asset_id' => $assetFile->id,
                'absolute_path' => $absolutePath,
                'detected_size' => $expectedSize,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
            abort(422, 'Template asset binary invalid.');
        }

        $sha256 = hash_file('sha256', $absolutePath);
        if (! is_string($sha256) || $sha256 === '') {
            Log::warning('Template asset hash generation failed', [
                'asset_id' => $assetFile->id,
                'absolute_path' => $absolutePath,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
            abort(422, 'Template asset binary invalid.');
        }

        $isPng = strcasecmp((string) $assetFile->mime_type, 'image/png') === 0
            || strcasecmp((string) $assetFile->file_ext, 'png') === 0;

        if ($isPng && ! $this->hasValidPngSignature($absolutePath)) {
            Log::warning('Template asset PNG signature invalid', [
                'asset_id' => $assetFile->id,
                'absolute_path' => $absolutePath,
                'expected_size' => $expectedSize,
                'sha256' => $sha256,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
            abort(422, 'Template asset PNG invalid.');
        }

        Log::info('Template asset stream start', [
            'asset_id' => $assetFile->id,
            'absolute_path' => $absolutePath,
            'file_size_bytes' => $expectedSize,
            'sha256' => $sha256,
            'mime_type' => $assetFile->mime_type,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        $this->prepareStreamingEnvironment();

        $contentType = $isPng ? 'image/png' : ($assetFile->mime_type ?: 'application/octet-stream');
        $rangeHeader = $request->header('Range');

        if (! is_string($rangeHeader) || $rangeHeader === '') {
            $offloadResponse = $this->buildOffloadResponse(
                absolutePath: $absolutePath,
                fileName: (string) $assetFile->file_name,
                contentType: $contentType,
                expectedSize: $expectedSize,
                assetId: (string) $assetFile->id,
                sha256: $sha256,
            );

            if ($offloadResponse instanceof Response) {
                return $offloadResponse;
            }
        }

        if (is_string($rangeHeader) && $rangeHeader !== '') {
            $range = $this->parseRangeHeader($rangeHeader, $expectedSize);

            if ($range === null) {
                Log::warning('Template asset invalid range request', [
                    'asset_id' => $assetFile->id,
                    'range' => $rangeHeader,
                    'file_size_bytes' => $expectedSize,
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                ]);

                return response('', 416, [
                    'Content-Range' => "bytes */{$expectedSize}",
                    'Accept-Ranges' => 'bytes',
                    'X-Asset-Id' => (string) $assetFile->id,
                    'X-Asset-Size' => (string) $expectedSize,
                    'X-Asset-Sha256' => $sha256,
                ]);
            }

            [$rangeStart, $rangeEnd] = $range;
            $rangeLength = ($rangeEnd - $rangeStart) + 1;

            Log::info('Template asset partial stream start', [
                'asset_id' => $assetFile->id,
                'range_start' => $rangeStart,
                'range_end' => $rangeEnd,
                'range_length' => $rangeLength,
                'file_size_bytes' => $expectedSize,
                'sha256' => $sha256,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);

            return response()->stream(
                function () use ($absolutePath, $rangeStart, $rangeLength, $assetFile): void {
                    $handle = fopen($absolutePath, 'rb');
                    if ($handle === false) {
                        Log::warning('Template asset partial stream open failed', [
                            'asset_id' => $assetFile->id,
                            'path' => $absolutePath,
                        ]);

                        return;
                    }

                    fseek($handle, $rangeStart);
                    $remaining = $rangeLength;
                    $chunkSize = 8192;
                    $bytesSent = 0;

                    while ($remaining > 0 && ! feof($handle)) {
                        if (connection_aborted()) {
                            break;
                        }

                        $readSize = min($chunkSize, $remaining);
                        $buffer = fread($handle, $readSize);

                        if ($buffer === false || $buffer === '') {
                            break;
                        }

                        $bufferLength = strlen($buffer);
                        $remaining -= $bufferLength;
                        $bytesSent += $bufferLength;
                        echo $buffer;
                        flush();
                    }

                    fclose($handle);

                    Log::info('Template asset partial stream end', [
                        'asset_id' => $assetFile->id,
                        'requested_length' => $rangeLength,
                        'bytes_sent' => $bytesSent,
                        'connection_aborted' => connection_aborted() === 1,
                    ]);
                },
                206,
                [
                    'Content-Type' => $contentType,
                    'Content-Disposition' => 'inline; filename="'.$assetFile->file_name.'"',
                    'Content-Length' => (string) $rangeLength,
                    'Content-Range' => "bytes {$rangeStart}-{$rangeEnd}/{$expectedSize}",
                    'Content-Encoding' => 'identity',
                    'Accept-Ranges' => 'bytes',
                    'Cache-Control' => 'public, max-age=604800, immutable',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Asset-Id' => (string) $assetFile->id,
                    'X-Asset-Size' => (string) $expectedSize,
                    'X-Asset-Sha256' => $sha256,
                ],
            );
        }

        return response()->stream(
            function () use ($absolutePath, $assetFile, $expectedSize): void {
                $handle = fopen($absolutePath, 'rb');
                if ($handle === false) {
                    Log::warning('Template asset full stream open failed', [
                        'asset_id' => $assetFile->id,
                        'path' => $absolutePath,
                    ]);

                    return;
                }

                $chunkSize = 8192;
                $bytesSent = 0;

                while (! feof($handle)) {
                    if (connection_aborted()) {
                        break;
                    }

                    $buffer = fread($handle, $chunkSize);
                    if ($buffer === false || $buffer === '') {
                        break;
                    }

                    $bytesSent += strlen($buffer);
                    echo $buffer;
                    flush();
                }

                fclose($handle);

                Log::info('Template asset full stream end', [
                    'asset_id' => $assetFile->id,
                    'expected_length' => $expectedSize,
                    'bytes_sent' => $bytesSent,
                    'connection_aborted' => connection_aborted() === 1,
                ]);
            },
            200,
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline; filename="'.$assetFile->file_name.'"',
                'Content-Length' => (string) $expectedSize,
                'Content-Encoding' => 'identity',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=604800, immutable',
                'X-Content-Type-Options' => 'nosniff',
                'X-Asset-Id' => (string) $assetFile->id,
                'X-Asset-Size' => (string) $expectedSize,
                'X-Asset-Sha256' => $sha256,
            ],
        );
    }

    public function diagnose(Request $request, AssetFile $assetFile): JsonResponse
    {
        $categoryAllowed = in_array($assetFile->file_category, ['template_overlay', 'template_thumbnail'], true);
        $diskExists = false;
        $absoluteFileExists = false;
        $actualSize = null;
        $sha256 = null;
        $pngSignatureValid = null;
        $diagnosticError = null;

        try {
            $disk = Storage::disk($assetFile->storage_disk);
            $diskExists = $disk->exists($assetFile->file_path);

            if ($diskExists) {
                $absolutePath = $disk->path($assetFile->file_path);
                $absoluteFileExists = is_file($absolutePath);

                if ($absoluteFileExists) {
                    $detectedSize = filesize($absolutePath);
                    $actualSize = is_int($detectedSize) ? $detectedSize : null;
                    $detectedHash = hash_file('sha256', $absolutePath);
                    $sha256 = is_string($detectedHash) && $detectedHash !== '' ? $detectedHash : null;

                    if ($this->isPngAsset($assetFile)) {
                        $pngSignatureValid = $this->hasValidPngSignature($absolutePath);
                    }
                }
            }
        } catch (Throwable $exception) {
            $diagnosticError = $exception->getMessage();
        }

        return response()->json([
            'asset_id' => $assetFile->id,
            'file_category' => $assetFile->file_category,
            'category_allowed' => $categoryAllowed,
            'storage_disk' => $assetFile->storage_disk,
            'file_path' => $assetFile->file_path,
            'file_name' => $assetFile->file_name,
            'file_ext' => $assetFile->file_ext,
            'mime_type' => $assetFile->mime_type,
            'db_file_size_bytes' => $assetFile->file_size_bytes,
            'disk_exists' => $diskExists,
            'absolute_file_exists' => $absoluteFileExists,
            'actual_file_size_bytes' => $actualSize,
            'sha256' => $sha256,
            'png_signature_valid' => $pngSignatureValid,
            'delivery_driver' => config('filesystems.template_assets.delivery_driver', 'php_stream'),
            'diagnostic_error' => $diagnosticError,
        ]);
    }

    private function hasValidPngSignature(string $absolutePath): bool
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $signature = fread($handle, 8);
        fclose($handle);

        return $signature === "\x89PNG\r\n\x1a\n";
    }

    private function isPngAsset(AssetFile $assetFile): bool
    {
        return strcasecmp((string) $assetFile->mime_type, 'image/png') === 0
            || strcasecmp((string) $assetFile->file_ext, 'png') === 0;
    }

    private function buildOffloadResponse(
        string $absolutePath,
        string $fileName,
        string $contentType,
        int $expectedSize,
        string $assetId,
        string $sha256,
    ): ?Response {
        $deliveryDriver = (string) config('filesystems.template_assets.delivery_driver', 'php_stream');
        $baseHeaders = [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            'Content-Length' => (string) $expectedSize,
            'Content-Encoding' => 'identity',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=604800, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'X-Asset-Id' => $assetId,
            'X-Asset-Size' => (string) $expectedSize,
            'X-Asset-Sha256' => $sha256,
        ];

        if ($deliveryDriver === 'x_sendfile') {
            $headerName = (string) config('filesystems.template_assets.x_sendfile_header', 'X-Sendfile');

            Log::info('Template asset offload x_sendfile', [
                'asset_id' => $assetId,
                'header' => $headerName,
                'path' => $absolutePath,
            ]);

            return response('', 200, [
                ...$baseHeaders,
                $headerName => $absolutePath,
            ]);
        }

        if ($deliveryDriver === 'x_accel_redirect') {
            $accelPath = $this->buildAccelPath($absolutePath);

            if ($accelPath === null) {
                Log::warning('Template asset x_accel path mapping failed', [
                    'asset_id' => $assetId,
                    'absolute_path' => $absolutePath,
                    'x_accel_root' => config('filesystems.template_assets.x_accel_root'),
                    'x_accel_prefix' => config('filesystems.template_assets.x_accel_prefix'),
                ]);

                return null;
            }

            Log::info('Template asset offload x_accel_redirect', [
                'asset_id' => $assetId,
                'x_accel_redirect' => $accelPath,
            ]);

            return response('', 200, [
                ...$baseHeaders,
                'X-Accel-Redirect' => $accelPath,
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return null;
    }

    private function buildAccelPath(string $absolutePath): ?string
    {
        $root = (string) config('filesystems.template_assets.x_accel_root', storage_path('app/public'));
        $prefix = (string) config('filesystems.template_assets.x_accel_prefix', '/internal-storage');
        $normalizedRoot = str_replace('\\', '/', rtrim($root, '/'));
        $normalizedPath = str_replace('\\', '/', $absolutePath);

        if (! Str::startsWith($normalizedPath, $normalizedRoot.'/') && $normalizedPath !== $normalizedRoot) {
            return null;
        }

        $relative = ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/');

        return rtrim($prefix, '/').'/'.$relative;
    }

    /**
     * @return array{int, int}|null
     */
    private function parseRangeHeader(string $rangeHeader, int $fileSize): ?array
    {
        if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
            return null;
        }

        $startRaw = $matches[1];
        $endRaw = $matches[2];

        if ($startRaw === '' && $endRaw === '') {
            return null;
        }

        if ($startRaw === '') {
            $suffixLength = (int) $endRaw;
            if ($suffixLength <= 0) {
                return null;
            }

            $start = max(0, $fileSize - $suffixLength);
            $end = $fileSize - 1;

            return [$start, $end];
        }

        $start = (int) $startRaw;
        $end = $endRaw === '' ? ($fileSize - 1) : (int) $endRaw;

        if ($start < 0 || $end < $start || $start >= $fileSize || $end >= $fileSize) {
            return null;
        }

        return [$start, $end];
    }

    private function prepareStreamingEnvironment(): void
    {
        ignore_user_abort(true);
        @set_time_limit(0);
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
    }
}
