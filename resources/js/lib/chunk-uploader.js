import { apiClient } from './api-client';

export const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
export const MAX_TOTAL_SIZE = 25 * 1024 * 1024; // 25 MB
export const MAX_FILES_COUNT = 5;
export const CHUNK_SIZE = 512 * 1024; // 512 KB per chunk

export const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg'];
export const ALLOWED_MIME_TYPES = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];

/**
 * Format bytes to readable string (e.g. 2.4 MB)
 */
export function formatBytes(bytes, decimals = 1) {
    if (!+bytes) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
}

/**
 * Validate a set of files against count, size, and type limits.
 */
export function validateAttachmentFiles(existingList = [], newFiles = []) {
    const totalCount = existingList.length + newFiles.length;
    if (totalCount > MAX_FILES_COUNT) {
        return {
            valid: false,
            error: `You can upload a maximum of ${MAX_FILES_COUNT} files (${totalCount} selected).`,
        };
    }

    let totalSize = existingList.reduce((acc, item) => acc + (item.file_size || item.file?.size || 0), 0);

    for (const file of newFiles) {
        const ext = file.name.split('.').pop()?.toLowerCase();
        if (!ext || !ALLOWED_EXTENSIONS.includes(ext)) {
            return {
                valid: false,
                error: `"${file.name}" is not an allowed format. Allowed formats: PDF, PNG, JPEG.`,
            };
        }

        if (file.size > MAX_FILE_SIZE) {
            return {
                valid: false,
                error: `"${file.name}" exceeds the maximum allowed single file size of 5 MB (${formatBytes(file.size)}).`,
            };
        }

        if (file.size === 0) {
            return {
                valid: false,
                error: `"${file.name}" is empty.`,
            };
        }

        totalSize += file.size;
    }

    if (totalSize > MAX_TOTAL_SIZE) {
        return {
            valid: false,
            error: `Total size of all attachments exceeds 25 MB limit (${formatBytes(totalSize)}).`,
        };
    }

    return { valid: true };
}

/**
 * Uploads a file chunk by chunk with automatic retry on network interruption.
 */
export async function uploadFileChunked(file, options = {}) {
    const { onProgress, signal, maxRetries = 3 } = options;

    const totalChunks = Math.max(1, Math.ceil(file.size / CHUNK_SIZE));
    const mimeType = file.type || (file.name.endsWith('.pdf') ? 'application/pdf' : 'image/png');

    // 1. Initialize chunk session
    onProgress?.({
        percent: 0,
        currentChunk: 0,
        totalChunks,
        bytesUploaded: 0,
        totalBytes: file.size,
        status: 'initializing',
    });

    const initRes = await apiClient.post('/attachments/chunk/init', {
        filename: file.name,
        file_size: file.size,
        mime_type: mimeType,
        total_chunks: totalChunks,
    }, { signal });

    const uploadId = initRes.data?.data?.upload_id;
    if (!uploadId) {
        throw new Error('Failed to initialize upload session.');
    }

    // 2. Upload chunks in sequential order with exponential backoff on retry
    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
        const start = chunkIndex * CHUNK_SIZE;
        const end = Math.min(file.size, start + CHUNK_SIZE);
        const chunkBlob = file.slice(start, end);

        let attempt = 0;
        let success = false;
        let lastError = null;

        while (attempt < maxRetries && !success) {
            attempt++;
            try {
                if (signal?.aborted) {
                    throw new Error('Upload cancelled');
                }

                const formData = new FormData();
                formData.append('upload_id', uploadId);
                formData.append('chunk_index', chunkIndex.toString());
                formData.append('chunk', chunkBlob, `chunk_${chunkIndex}`);

                await apiClient.post('/attachments/chunk/upload', formData, {
                    signal,
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                });

                success = true;
            } catch (err) {
                lastError = err;
                if (signal?.aborted) throw err;
                if (attempt < maxRetries) {
                    // Backoff delay before retrying this chunk (500ms, 1000ms...)
                    await new Promise((r) => setTimeout(r, attempt * 500));
                }
            }
        }

        if (!success) {
            throw new Error(`Failed to upload chunk ${chunkIndex + 1}/${totalChunks}: ${lastError?.message || 'Network error'}`);
        }

        const bytesUploaded = end;
        const percent = Math.round((bytesUploaded / file.size) * 95); // 95% until merged

        onProgress?.({
            percent,
            currentChunk: chunkIndex + 1,
            totalChunks,
            bytesUploaded,
            totalBytes: file.size,
            status: 'uploading',
        });
    }

    // 3. Complete and merge upload
    onProgress?.({
        percent: 98,
        currentChunk: totalChunks,
        totalChunks,
        bytesUploaded: file.size,
        totalBytes: file.size,
        status: 'finalizing',
    });

    const completeRes = await apiClient.post('/attachments/chunk/complete', {
        upload_id: uploadId,
    }, { signal });

    const attachment = completeRes.data?.data;

    onProgress?.({
        percent: 100,
        currentChunk: totalChunks,
        totalChunks,
        bytesUploaded: file.size,
        totalBytes: file.size,
        status: 'completed',
    });

    return attachment;
}
