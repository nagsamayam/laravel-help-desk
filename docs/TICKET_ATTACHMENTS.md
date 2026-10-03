# Ticket Attachment Subsystem

## Overview

The HelpDesk platform features a robust, production-grade **Ticket Attachment Subsystem** supporting multi-file uploads during ticket creation, resilient chunked file streaming with exponential backoff retries, local storage persistence with AWS S3 pre-signed URL generation, and role-based authorized viewing and downloading.

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                   React 19 Frontend                                    │
│                                                                                        │
│   ┌───────────────────────────┐                ┌───────────────────────────────────┐   │
│   │   TicketCreateModal.jsx   │                │         TicketDetail.jsx          │   │
│   │  (Dropzone + Progress)    │                │  (In-App Preview & Blob Download) │   │
│   └─────────────┬─────────────┘                └─────────────────▲─────────────────┘   │
│                 │                                                │                     │
│                 ▼                                                │                     │
│   ┌───────────────────────────┐                                  │ Authenticated       │
│   │     chunk-uploader.js     │                                  │ Blob GET with       │
│   │ (Slicing + Backoff Retry) │                                  │ Bearer Auth         │
│   └─────────────┬─────────────┘                                  │                     │
└─────────────────┼────────────────────────────────────────────────┼─────────────────────┘
                  │                                                │
                  │ Chunk Upload API (HTTP / JSON)                 │
                  ▼                                                │
┌──────────────────────────────────────────────────────────────────┴─────────────────────┐
│                                Laravel API Backend                                     │
│                                                                                        │
│   ┌─────────────────────────────┐                 ┌────────────────────────────────┐   │
│   │    AttachmentController     │                 │      AttachmentController      │   │
│   │ (/chunk/init, chunk, comp.) │                 │  (view / download streaming)   │   │
│   └─────────────┬───────────────┘                 └────────────────▲───────────────┘   │
│                 │                                                  │                   │
│                 ▼                                                  │                   │
│   ┌─────────────────────────────┐                 ┌────────────────┴───────────────┐   │
│   │      AttachmentService      │◄───────────────►│     TicketAttachmentPolicy     │   │
│   │  (Assembly, Disk, Cleanup)  │                 │   (Role & Ownership Checks)    │   │
│   └─────────────┬───────────────┘                 └────────────────────────────────┘   │
└─────────────────┼──────────────────────────────────────────────────────────────────────┘
                  ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              Storage Persistence Layer                                 │
│                                                                                        │
│   ┌─────────────────────────────────────────┐   ┌──────────────────────────────────┐   │
│   │       Local Storage Disk (Default)      │   │     AWS S3 Bucket (Production)   │   │
│   │       storage/app/attachments/          │   │  Pre-signed Multi-part URLs      │   │
│   └─────────────────────────────────────────┘   └──────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 1. Specifications & Constraints

The attachment subsystem enforces strict file-level and batch-level validation rules across both the React frontend and Laravel backend:

| Constraint | Limit | Description |
| :--- | :--- | :--- |
| **Maximum File Count** | 5 files | A single ticket cannot exceed 5 total attachments. |
| **Max Individual File Size** | 5 MB (`5,242,880 bytes`) | Any individual file larger than 5 MB is rejected before/during upload. |
| **Max Cumulative Size** | 25 MB (`26,214,400 bytes`) | The total payload size of all attachments for a single ticket cannot exceed 25 MB. |
| **Allowed File Formats** | `PDF`, `PNG`, `JPEG / JPG` | Validated by both client file extension and server-side MIME type inspection. |
| **Allowed MIME Types** | `application/pdf`, `image/png`, `image/jpeg` | Enforced via PHP `finfo` file signature validation. |
| **Default Chunk Size** | 1 MB (`1,048,576 bytes`) | Configurable slice size for resilient chunked streaming. |

---

## 2. Chunked Upload Architecture & Workflow

Large files or unstable network connections can fail midway through upload. The chunked uploader slices files into 1 MB chunks and uploads them sequentially with retry logic.

### 3-Phase Chunking Lifecycle

```text
Client                                                              Server
  │                                                                    │
  │─── 1. POST /api/v1/attachments/chunk/init ─────────────────────────▶│ (Validates file metadata,
  │    { file_name, file_size, mime_type, total_chunks }               │  allocates temp storage)
  │◀── Return { upload_id, chunk_size, total_chunks } ─────────────────│
  │                                                                    │
  │─── 2. POST /api/v1/attachments/chunk ──────────────────────────────▶│ (Stores chunk to temp folder)
  │    (FormData: upload_id, chunk_index, chunk_data)                  │
  │◀── Return { success: true, chunk_index } ──────────────────────────│
  │    (Repeat for chunks 0 ... N-1 with auto-retry)                  │
  │                                                                    │
  │─── 3. POST /api/v1/attachments/chunk/complete ─────────────────────▶│ (Validates all chunks exist,
  │    { upload_id }                                                   │  merges into final file,
  │                                                                    │  creates TicketAttachment DB record)
  │◀── Return { attachment_id, original_name, file_size, url } ────────│
  │                                                                    │
  │─── 4. POST /api/v1/tickets ────────────────────────────────────────▶│ (Links attachment_ids to ticket)
  │    { subject, description, attachment_ids: [1, 2] }                │
```

#### Step 1: Chunk Initialization (`POST /api/v1/attachments/chunk/init`)
- Validates file extension, MIME type, and file size (<= 5 MB).
- Generates a unique `upload_id` (UUIDv4) and creates a temporary chunk directory at `storage/app/chunks/{upload_id}/`.
- Saves a metadata manifest (`manifest.json`) recording expected chunk counts and original filename.

#### Step 2: Uploading Chunks (`POST /api/v1/attachments/chunk`)
- Receives a chunk payload via `multipart/form-data` with `upload_id` and `chunk_index`.
- Stores the slice as `chunk_{index}.part` in the temporary upload folder.
- If a network drop occurs during a chunk upload, the client automatically retries up to 3 times with exponential backoff (1s, 2s, 4s delay) without having to re-upload previously completed chunks.

#### Step 3: Chunk Assembly & Completion (`POST /api/v1/attachments/chunk/complete`)
- Verifies that all slices from index `0` to `total_chunks - 1` exist on disk.
- Streams each chunk into a single finalized file using PHP memory-safe stream buffers (`fopen`, `stream_copy_to_stream`).
- Inspects the assembled file's true MIME type using PHP `finfo_file` to prevent MIME-spoofing attacks.
- Cleans up the temporary chunk folder.
- Creates an unattached `TicketAttachment` database record (`ticket_id = null`) linked to the authenticated user.

#### Step 4: Associating with Ticket (`POST /api/v1/tickets`)
- When the user submits the ticket creation form, the array of completed `attachment_ids: [id1, id2, ...]` is passed in the ticket payload.
- `CreateTicketAction` assigns the newly created `ticket_id` to each attachment record and ensures that:
  - No more than 5 attachments are attached.
  - The cumulative size of all attachments is <= 25 MB.
  - The authenticated user is the owner who uploaded the temporary attachments.

---

## 3. Storage Drivers & AWS S3 Compatibility

The subsystem supports both local storage and cloud object storage (AWS S3):

### Local Storage (Default)
- Files are saved with sanitized UUID names under `storage/app/attachments/{year}/{month}/{uuid}.{ext}`.
- Direct web access to `storage/app/attachments` is blocked for security; all file access goes through authorized streaming endpoints.

### AWS S3 Storage & Pre-signed URLs
For enterprise cloud deployments, the API provides direct-to-S3 pre-signed upload URL generation:

- **Endpoint:** `POST /api/v1/attachments/presigned-url`
- **Request Body:**
  ```json
  {
    "file_name": "network_diagram.png",
    "file_size": 2048576,
    "mime_type": "image/png"
  }
  ```
- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "upload_url": "https://helpdesk-bucket.s3.amazonaws.com/attachments/2026/10/uuid.png?AWSAccessKeyId=...",
      "file_path": "attachments/2026/10/uuid.png",
      "disk": "s3",
      "expires_at": "2026-10-03T14:30:00Z"
    }
  }
  ```

---

## 4. Database Schema (`ticket_attachments`)

```sql
CREATE TABLE `ticket_attachments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` BIGINT UNSIGNED NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    
    INDEX `idx_ticket_attachments_ticket_id` (`ticket_id`),
    INDEX `idx_ticket_attachments_user_id` (`user_id`),
    CONSTRAINT `fk_ticket_attachments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_attachments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Security & Authorization

### Role-Based Access Control (`TicketAttachmentPolicy`)
File access is governed by strict authorization checks:
- **Admin**: Can view and download all attachments across all tickets.
- **Agent**: Can view and download attachments on any assigned ticket or open ticket within their scope.
- **Customer**: Can **only** view and download attachments on tickets that they personally own (`customer_id === user.id`).
- **Unattached Files**: Only the user who uploaded a pending temporary attachment can view or attach it.

### Secure File Delivery
- **Inline Preview:** `GET /api/v1/attachments/{id}/view` returns `Content-Disposition: inline; filename="..."` with verified `Content-Type: image/png` or `application/pdf`, enabling in-browser rendering.
- **Forced Download:** `GET /api/v1/attachments/{id}/download` returns `Content-Disposition: attachment; filename="..."`, forcing the browser to prompt the user to save the file.
- **Authentication:** Supports standard `Authorization: Bearer <token>` headers as well as signed query parameter authentication for standalone browser tabs.

---

## 6. Frontend Integration & UI Components

### 1. Chunk Uploader Utility (`resources/js/lib/chunk-uploader.js`)
- Exposes `uploadFileInChunks(file, { onProgress, maxRetries: 3 })`.
- Handles binary slicing via `Blob.prototype.slice()`.
- Calculates accurate aggregate progress percentages across all chunks.

### 2. Attachment Dropzone (`resources/js/features/tickets/TicketCreateModal.jsx`)
- Drag-and-drop file upload zone with visual validation for file count, individual size (<= 5 MB), and cumulative total size (<= 25 MB).
- Per-file progress bars, real-time status indicators (Uploading, Completed, Failed), and retry buttons.
- Prevents ticket submission until all files are successfully uploaded and IDs are acquired.

### 3. Attachment Viewer & Download (`resources/js/features/tickets/TicketDetail.jsx`)
- Attachment pills displaying file type icons, humanized sizes (e.g. `2.4 MB`), and view/download buttons.
- **In-App Preview Modal:** Embedded image renderer and PDF viewer modal using authenticated blob object URLs (`URL.createObjectURL(blob)`), eliminating unauthenticated 401 link navigation errors.
- Automatic blob cleanup on modal close (`URL.revokeObjectURL`).

---

## 7. Testing & Verification

The attachment subsystem is thoroughly tested in `tests/Feature/AttachmentTest.php`:
- `test_user_can_initialize_chunked_upload()`
- `test_user_can_upload_chunks_and_complete_assembly()`
- `test_chunk_upload_retries_resiliently_on_transient_failure()`
- `test_file_size_limit_validation()`
- `test_mime_type_validation_rejects_unauthorized_extensions()`
- `test_cumulative_25mb_ticket_limit_enforcement()`
- `test_customer_cannot_download_other_customer_attachments()`
- `test_admin_and_agent_can_view_ticket_attachments()`
