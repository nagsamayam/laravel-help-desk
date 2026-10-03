<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\TicketAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    public const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

    public const MAX_TOTAL_SIZE_BYTES = 25 * 1024 * 1024; // 25 MB

    public const MAX_FILES_COUNT = 5;

    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/jpg',
    ];

    public const ALLOWED_EXTENSIONS = [
        'pdf',
        'png',
        'jpg',
        'jpeg',
    ];

    /**
     * Initializes a chunked upload session.
     *
     * @return array{upload_id: string, chunk_size: int, total_chunks: int}
     */
    public function initChunkUpload(User $user, string $filename, int $fileSize, string $mimeType, int $totalChunks): array
    {
        $this->validateFileMetadata($filename, $fileSize, $mimeType);

        if ($totalChunks < 1 || $totalChunks > 500) {
            throw ValidationException::withMessages([
                'total_chunks' => ['Total chunks must be between 1 and 500.'],
            ]);
        }

        $uploadId = (string) Str::uuid();
        $chunkDir = storage_path("app/chunks/{$uploadId}");

        if (! File::isDirectory($chunkDir)) {
            File::makeDirectory($chunkDir, 0755, true);
        }

        $metadata = [
            'upload_id' => $uploadId,
            'user_id' => $user->id,
            'filename' => $filename,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'total_chunks' => $totalChunks,
            'created_at' => now()->timestamp,
        ];

        Cache::put("chunk_upload:{$uploadId}", $metadata, now()->addHours(6));

        return [
            'upload_id' => $uploadId,
            'chunk_size' => (int) ceil($fileSize / $totalChunks),
            'total_chunks' => $totalChunks,
        ];
    }

    /**
     * Stores an individual chunk slice.
     *
     * @return array{upload_id: string, chunk_index: int, uploaded_chunks: array<int>, total_chunks: int}
     */
    public function uploadChunk(User $user, string $uploadId, int $chunkIndex, UploadedFile $chunkFile): array
    {
        $metadata = Cache::get("chunk_upload:{$uploadId}");

        if (! $metadata || (int) $metadata['user_id'] !== $user->id) {
            throw ValidationException::withMessages([
                'upload_id' => ['Invalid or expired upload session.'],
            ]);
        }

        $totalChunks = (int) $metadata['total_chunks'];
        if ($chunkIndex < 0 || $chunkIndex >= $totalChunks) {
            throw ValidationException::withMessages([
                'chunk_index' => ["Chunk index must be between 0 and {$totalChunks} - 1."],
            ]);
        }

        $chunkDir = storage_path("app/chunks/{$uploadId}");
        if (! File::isDirectory($chunkDir)) {
            File::makeDirectory($chunkDir, 0755, true);
        }

        $chunkPath = "{$chunkDir}/chunk_{$chunkIndex}";
        if (file_exists($chunkPath)) {
            @unlink($chunkPath);
        }

        $realPath = $chunkFile->getRealPath();
        if ($realPath && file_exists($realPath)) {
            file_put_contents($chunkPath, file_get_contents($realPath));
        } else {
            $chunkFile->move($chunkDir, "chunk_{$chunkIndex}");
        }

        $uploadedChunks = $this->getUploadedChunkIndices($uploadId, $totalChunks);

        return [
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'uploaded_chunks' => $uploadedChunks,
            'total_chunks' => $totalChunks,
        ];
    }

    /**
     * Merges all uploaded chunks into the final destination and creates a TicketAttachment.
     */
    public function completeChunkUpload(User $user, string $uploadId): TicketAttachment
    {
        $metadata = Cache::get("chunk_upload:{$uploadId}");

        if (! $metadata || (int) $metadata['user_id'] !== $user->id) {
            throw ValidationException::withMessages([
                'upload_id' => ['Invalid or expired upload session.'],
            ]);
        }

        $totalChunks = (int) $metadata['total_chunks'];
        $uploadedChunks = $this->getUploadedChunkIndices($uploadId, $totalChunks);

        if (count($uploadedChunks) !== $totalChunks) {
            throw ValidationException::withMessages([
                'upload_id' => ['Missing chunks. Upload cannot be completed yet.'],
            ]);
        }

        $chunkDir = storage_path("app/chunks/{$uploadId}");
        $tempCombinedPath = storage_path("app/chunks/{$uploadId}_combined");

        $combinedFile = fopen($tempCombinedPath, 'wb');
        if (! $combinedFile) {
            throw new \RuntimeException('Failed to create merged attachment file.');
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFilePath = "{$chunkDir}/chunk_{$i}";
            if (! file_exists($chunkFilePath)) {
                fclose($combinedFile);
                @unlink($tempCombinedPath);
                throw ValidationException::withMessages([
                    'upload_id' => ["Chunk {$i} is missing."],
                ]);
            }

            $chunkHandle = fopen($chunkFilePath, 'rb');
            if ($chunkHandle) {
                while (! feof($chunkHandle)) {
                    $buffer = fread($chunkHandle, 8192);
                    if ($buffer !== false) {
                        fwrite($combinedFile, $buffer);
                    }
                }
                fclose($chunkHandle);
            }
        }
        fclose($combinedFile);

        $mergedSize = filesize($tempCombinedPath);
        if ($mergedSize > self::MAX_FILE_SIZE_BYTES) {
            @unlink($tempCombinedPath);
            File::deleteDirectory($chunkDir);
            Cache::forget("chunk_upload:{$uploadId}");
            throw ValidationException::withMessages([
                'file' => ['Merged file exceeds maximum size limit of 5MB.'],
            ]);
        }

        // Validate merged file MIME type
        $detectedMime = mime_content_type($tempCombinedPath) ?: 'application/octet-stream';
        if (! in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            @unlink($tempCombinedPath);
            File::deleteDirectory($chunkDir);
            Cache::forget("chunk_upload:{$uploadId}");
            throw ValidationException::withMessages([
                'file' => ['File content format is not allowed. Allowed formats: pdf, png, jpeg.'],
            ]);
        }

        $disk = config('filesystems.default', 'public');
        $ext = pathinfo($metadata['filename'], PATHINFO_EXTENSION) ?: 'bin';
        $finalPath = 'attachments/'.date('Y/m').'/'.Str::uuid().'.'.$ext;

        $stream = fopen($tempCombinedPath, 'rb');
        Storage::disk($disk)->put($finalPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Cleanup temporary chunk folder and temp file
        @unlink($tempCombinedPath);
        File::deleteDirectory($chunkDir);
        Cache::forget("chunk_upload:{$uploadId}");

        return TicketAttachment::query()->create([
            'ticket_id' => null,
            'user_id' => $user->id,
            'original_name' => $metadata['filename'],
            'file_path' => $finalPath,
            'disk' => $disk,
            'mime_type' => $detectedMime,
            'file_size' => $mergedSize,
        ]);
    }

    /**
     * Direct upload single file.
     */
    public function storeDirectUpload(User $user, UploadedFile $file): TicketAttachment
    {
        $this->validateFileMetadata(
            $file->getClientOriginalName(),
            $file->getSize(),
            $file->getMimeType() ?: 'application/octet-stream'
        );

        $disk = config('filesystems.default', 'public');
        $path = $file->store('attachments/'.date('Y/m'), $disk);

        return TicketAttachment::query()->create([
            'ticket_id' => null,
            'user_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
        ]);
    }

    /**
     * Generates a pre-signed S3 URL or direct upload target.
     *
     * @return array<string, mixed>
     */
    public function generatePresignedUrl(User $user, string $filename, int $fileSize, string $mimeType): array
    {
        $this->validateFileMetadata($filename, $fileSize, $mimeType);

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin');
        $fileKey = 'attachments/'.date('Y/m').'/'.Str::uuid().'.'.$ext;
        $diskName = config('filesystems.default', 'public');

        if ($diskName === 's3') {
            try {
                $storage = Storage::disk('s3');
                if (method_exists($storage, 'temporaryUploadUrl')) {
                    $url = $storage->temporaryUploadUrl($fileKey, now()->addMinutes(30), [
                        'ContentType' => $mimeType,
                    ]);

                    return [
                        'driver' => 's3',
                        'upload_url' => $url,
                        'file_key' => $fileKey,
                        'method' => 'PUT',
                        'headers' => [
                            'Content-Type' => $mimeType,
                        ],
                    ];
                }
            } catch (\Throwable) {
                // Fallback to local
            }
        }

        return [
            'driver' => $diskName,
            'upload_url' => url('/api/v1/attachments/upload'),
            'file_key' => $fileKey,
            'method' => 'POST',
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];
    }

    /**
     * Registers an S3 pre-signed uploaded object as a TicketAttachment.
     */
    public function completePresignedUpload(User $user, string $fileKey, string $originalName, int $fileSize, string $mimeType): TicketAttachment
    {
        $this->validateFileMetadata($originalName, $fileSize, $mimeType);

        $disk = 's3';

        return TicketAttachment::query()->create([
            'ticket_id' => null,
            'user_id' => $user->id,
            'original_name' => $originalName,
            'file_path' => $fileKey,
            'disk' => $disk,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
        ]);
    }

    /**
     * Download an attachment file response.
     */
    public function downloadResponse(TicketAttachment $attachment): BinaryFileResponse|StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if ($attachment->disk === 's3') {
            return $disk->download($attachment->file_path, $attachment->original_name);
        }

        $localPath = $disk->path($attachment->file_path);
        if (! file_exists($localPath)) {
            abort(404, 'Attachment file not found on disk.');
        }

        return response()->download($localPath, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    /**
     * View/stream an attachment file inline in browser.
     */
    public function viewResponse(TicketAttachment $attachment): BinaryFileResponse|StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if ($attachment->disk === 's3') {
            return $disk->response($attachment->file_path, $attachment->original_name, [
                'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
            ]);
        }

        $localPath = $disk->path($attachment->file_path);
        if (! file_exists($localPath)) {
            abort(404, 'Attachment file not found on disk.');
        }

        return response()->file($localPath, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
        ]);
    }

    /**
     * Validates file size, extension, and mime type constraints.
     */
    public function validateFileMetadata(string $filename, int $fileSize, string $mimeType): void
    {
        if ($fileSize <= 0) {
            throw ValidationException::withMessages([
                'file_size' => ['File cannot be empty.'],
            ]);
        }

        if ($fileSize > self::MAX_FILE_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'file_size' => ['File size exceeds the 5MB limit.'],
            ]);
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'filename' => ['Invalid file format. Allowed formats: pdf, png, jpeg.'],
            ]);
        }

        if (! in_array(strtolower($mimeType), self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'mime_type' => ['Invalid file MIME type. Allowed formats: pdf, png, jpeg.'],
            ]);
        }
    }

    /**
     * Returns list of uploaded chunk indices.
     *
     * @return array<int>
     */
    private function getUploadedChunkIndices(string $uploadId, int $totalChunks): array
    {
        $chunkDir = storage_path("app/chunks/{$uploadId}");
        if (! File::isDirectory($chunkDir)) {
            return [];
        }

        $uploaded = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            if (file_exists("{$chunkDir}/chunk_{$i}")) {
                $uploaded[] = $i;
            }
        }

        return $uploaded;
    }
}
