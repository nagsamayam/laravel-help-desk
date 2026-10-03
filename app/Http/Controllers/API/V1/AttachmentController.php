<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Domain\Ticket\Models\TicketAttachment;
use App\Domain\Ticket\Services\AttachmentService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TicketAttachmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachmentService,
    ) {}

    /**
     * Initializes a chunked upload.
     */
    public function initChunk(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.AttachmentService::MAX_FILE_SIZE_BYTES],
            'mime_type' => ['required', 'string'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $result = $this->attachmentService->initChunkUpload(
            user: $request->user(),
            filename: (string) $request->input('filename'),
            fileSize: (int) $request->input('file_size'),
            mimeType: (string) $request->input('mime_type'),
            totalChunks: (int) $request->input('total_chunks'),
        );

        return response()->json([
            'data' => $result,
        ], Response::HTTP_CREATED);
    }

    /**
     * Uploads an individual chunk slice.
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => ['required', 'string', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file'],
        ]);

        $result = $this->attachmentService->uploadChunk(
            user: $request->user(),
            uploadId: (string) $request->input('upload_id'),
            chunkIndex: (int) $request->input('chunk_index'),
            chunkFile: $request->file('chunk'),
        );

        return response()->json([
            'data' => $result,
        ], Response::HTTP_OK);
    }

    /**
     * Completes and merges chunks into a final TicketAttachment.
     */
    public function completeChunk(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => ['required', 'string', 'uuid'],
        ]);

        $attachment = $this->attachmentService->completeChunkUpload(
            user: $request->user(),
            uploadId: (string) $request->input('upload_id'),
        );

        return response()->json([
            'data' => (new TicketAttachmentResource($attachment))->resolve($request),
        ], Response::HTTP_CREATED);
    }

    /**
     * Direct single file upload.
     */
    public function directUpload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf,png,jpg,jpeg',
                'max:5120', // 5 MB
            ],
        ]);

        $attachment = $this->attachmentService->storeDirectUpload(
            user: $request->user(),
            file: $request->file('file'),
        );

        return response()->json([
            'data' => (new TicketAttachmentResource($attachment))->resolve($request),
        ], Response::HTTP_CREATED);
    }

    /**
     * Generates a pre-signed S3 URL for direct client upload.
     */
    public function presignedUrl(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.AttachmentService::MAX_FILE_SIZE_BYTES],
            'mime_type' => ['required', 'string'],
        ]);

        $result = $this->attachmentService->generatePresignedUrl(
            user: $request->user(),
            filename: (string) $request->input('filename'),
            fileSize: (int) $request->input('file_size'),
            mimeType: (string) $request->input('mime_type'),
        );

        return response()->json([
            'data' => $result,
        ], Response::HTTP_OK);
    }

    /**
     * Completes and registers a pre-signed S3 upload.
     */
    public function completePresigned(Request $request): JsonResponse
    {
        $request->validate([
            'file_key' => ['required', 'string'],
            'original_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.AttachmentService::MAX_FILE_SIZE_BYTES],
            'mime_type' => ['required', 'string'],
        ]);

        $attachment = $this->attachmentService->completePresignedUpload(
            user: $request->user(),
            fileKey: (string) $request->input('file_key'),
            originalName: (string) $request->input('original_name'),
            fileSize: (int) $request->input('file_size'),
            mimeType: (string) $request->input('mime_type'),
        );

        return response()->json([
            'data' => (new TicketAttachmentResource($attachment))->resolve($request),
        ], Response::HTTP_CREATED);
    }

    /**
     * Downloads an attachment.
     */
    public function download(TicketAttachment $attachment): BinaryFileResponse|StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $this->attachmentService->downloadResponse($attachment);
    }

    /**
     * Views an attachment inline.
     */
    public function view(TicketAttachment $attachment): BinaryFileResponse|StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $this->attachmentService->viewResponse($attachment);
    }

    /**
     * Deletes an attachment.
     */
    public function destroy(TicketAttachment $attachment): JsonResponse
    {
        Gate::authorize('delete', $attachment);

        try {
            Storage::disk($attachment->disk)->delete($attachment->file_path);
        } catch (\Throwable) {
            // Ignore if file already missing from disk
        }

        $attachment->delete();

        return response()->json([
            'message' => 'Attachment deleted successfully.',
        ], Response::HTTP_OK);
    }
}
