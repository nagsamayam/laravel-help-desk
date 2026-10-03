<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

test('customer can upload file directly within 5MB limit and allowed format', function () {
    $customer = User::factory()->customer()->create();

    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/upload', [
            'file' => $file,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'original_name',
                'file_size',
                'mime_type',
                'is_pdf',
                'is_image',
                'download_url',
                'view_url',
            ],
        ]);

    $attachmentId = $response->json('data.id');
    expect(TicketAttachment::find($attachmentId))->not->toBeNull();
});

test('direct upload rejects files exceeding 5MB or invalid format', function () {
    $customer = User::factory()->customer()->create();

    // 6MB file exceeds 5MB limit
    $largeFile = UploadedFile::fake()->create('large.pdf', 6 * 1024, 'application/pdf');

    $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/upload', [
            'file' => $largeFile,
        ])
        ->assertUnprocessable();

    // Disallowed format (.zip)
    $zipFile = UploadedFile::fake()->create('archive.zip', 500, 'application/zip');

    $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/upload', [
            'file' => $zipFile,
        ])
        ->assertUnprocessable();
});

test('chunked upload flow allows uploading file in parts and merging them', function () {
    $customer = User::factory()->customer()->create();

    // 1. Init chunk upload for a 3MB PDF file split into 3 chunks of 1MB each
    $initResponse = $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/chunk/init', [
            'filename' => 'report.pdf',
            'file_size' => 3 * 1024 * 1024,
            'mime_type' => 'application/pdf',
            'total_chunks' => 3,
        ]);

    $initResponse->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'upload_id',
                'chunk_size',
                'total_chunks',
            ],
        ]);

    $uploadId = $initResponse->json('data.upload_id');

    // Create 3 chunks of a valid PDF content (%PDF- header)
    $chunk0Data = "%PDF-1.4\n".str_repeat('A', (1024 * 1024) - 9);
    $chunk1Data = str_repeat('B', 1024 * 1024);
    $chunk2Data = str_repeat('C', (1024 * 1024) - 6)."%%EOF\n";

    $chunkFile0 = UploadedFile::fake()->createWithContent('chunk_0', $chunk0Data);
    $chunkFile1 = UploadedFile::fake()->createWithContent('chunk_1', $chunk1Data);
    $chunkFile2 = UploadedFile::fake()->createWithContent('chunk_2', $chunk2Data);

    // 2. Upload chunk 0
    $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/chunk/upload', [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'chunk' => $chunkFile0,
        ])
        ->assertOk()
        ->assertJsonPath('data.uploaded_chunks', [0]);

    // 3. Upload chunk 1
    $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/chunk/upload', [
            'upload_id' => $uploadId,
            'chunk_index' => 1,
            'chunk' => $chunkFile1,
        ])
        ->assertOk()
        ->assertJsonPath('data.uploaded_chunks', [0, 1]);

    // Simulate network retry on chunk 1 (idempotent chunk upload)
    $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/chunk/upload', [
            'upload_id' => $uploadId,
            'chunk_index' => 1,
            'chunk' => $chunkFile1,
        ])
        ->assertOk();

    // 4. Upload chunk 2
    $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/chunk/upload', [
            'upload_id' => $uploadId,
            'chunk_index' => 2,
            'chunk' => $chunkFile2,
        ])
        ->assertOk()
        ->assertJsonPath('data.uploaded_chunks', [0, 1, 2]);

    // 5. Complete chunk upload
    $completeResponse = $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/chunk/complete', [
            'upload_id' => $uploadId,
        ]);

    $completeResponse->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'original_name',
                'file_size',
                'mime_type',
            ],
        ]);

    $attachmentId = $completeResponse->json('data.id');
    $attachment = TicketAttachment::find($attachmentId);
    expect($attachment)->not->toBeNull();
    expect($attachment->original_name)->toBe('report.pdf');
    expect($attachment->ticket_id)->toBeNull();
});

test('customer can create ticket with multiple attachments up to 5 files and 25MB total', function () {
    $customer = User::factory()->customer()->create();
    $category = Category::factory()->create(['is_active' => true]);

    $attachment1 = TicketAttachment::factory()->unattached()->forUser($customer)->create([
        'original_name' => 'doc1.pdf',
        'file_size' => 2 * 1024 * 1024, // 2MB
        'mime_type' => 'application/pdf',
    ]);

    $attachment2 = TicketAttachment::factory()->unattached()->forUser($customer)->image()->create([
        'original_name' => 'screenshot.png',
        'file_size' => 3 * 1024 * 1024, // 3MB
        'mime_type' => 'image/png',
    ]);

    $response = $this->actingAs($customer, 'api')
        ->withHeader('Idempotency-Key', Str::uuid()->toString())
        ->postJson('/api/v1/tickets', [
            'subject' => 'Cannot log into my portal',
            'description' => 'Attached screenshots and error log',
            'category_id' => $category->id,
            'priority' => 'HIGH',
            'attachment_ids' => [$attachment1->id, $attachment2->id],
        ]);

    $response->assertCreated();
    $ticketId = $response->json('data.id');

    expect($attachment1->fresh()->ticket_id)->toBe($ticketId);
    expect($attachment2->fresh()->ticket_id)->toBe($ticketId);

    $ticket = Ticket::with('attachments')->find($ticketId);
    expect($ticket->attachments)->toHaveCount(2);
});

test('ticket creation rejects more than 5 attachments or total size exceeding 25MB', function () {
    $customer = User::factory()->customer()->create();
    $category = Category::factory()->create(['is_active' => true]);

    // 6 attachments (exceeds max 5 count)
    $attachments = TicketAttachment::factory()->count(6)->unattached()->forUser($customer)->create([
        'file_size' => 1024 * 1024,
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($customer, 'api')
        ->withHeader('Idempotency-Key', Str::uuid()->toString())
        ->postJson('/api/v1/tickets', [
            'subject' => 'Ticket with too many files',
            'description' => 'Test description',
            'category_id' => $category->id,
            'priority' => 'LOW',
            'attachment_ids' => $attachments->pluck('id')->toArray(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    // 5 attachments but total size 26MB (exceeds 25MB total limit)
    $largeAttachments = TicketAttachment::factory()->count(5)->unattached()->forUser($customer)->create([
        'file_size' => (int) (5.2 * 1024 * 1024), // each is slightly above 5MB and total is 26MB
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($customer, 'api')
        ->withHeader('Idempotency-Key', Str::uuid()->toString())
        ->postJson('/api/v1/tickets', [
            'subject' => 'Ticket with oversized files',
            'description' => 'Test description',
            'category_id' => $category->id,
            'priority' => 'LOW',
            'attachment_ids' => $largeAttachments->pluck('id')->toArray(),
        ])
        ->assertUnprocessable();
});

test('attachment download and view authorization rules', function () {
    $ownerCustomer = User::factory()->customer()->create();
    $otherCustomer = User::factory()->customer()->create();
    $agent = User::factory()->agent()->create();

    $ticket = Ticket::factory()->forCustomer($ownerCustomer)->create();
    $attachment = TicketAttachment::factory()->forTicket($ticket)->forUser($ownerCustomer)->create([
        'file_path' => 'attachments/test.pdf',
        'mime_type' => 'application/pdf',
        'original_name' => 'test.pdf',
    ]);

    Storage::disk('public')->put('attachments/test.pdf', '%PDF-1.4 ... %%EOF');

    // Owner customer can download and view
    $this->actingAs($ownerCustomer, 'api')
        ->get("/api/v1/attachments/{$attachment->id}/download")
        ->assertOk();

    $this->actingAs($ownerCustomer, 'api')
        ->get("/api/v1/attachments/{$attachment->id}/view")
        ->assertOk();

    // Owner customer can also download using query string token
    $token = auth('api')->tokenById($ownerCustomer->id);
    $this->get("/api/v1/attachments/{$attachment->id}/download?token={$token}")
        ->assertOk();
    $this->get("/api/v1/attachments/{$attachment->id}/view?token={$token}")
        ->assertOk();

    // Agent can download and view
    $this->actingAs($agent, 'api')
        ->get("/api/v1/attachments/{$attachment->id}/download")
        ->assertOk();

    // Other customer is forbidden
    $this->actingAs($otherCustomer, 'api')
        ->get("/api/v1/attachments/{$attachment->id}/download")
        ->assertForbidden();

    $this->actingAs($otherCustomer, 'api')
        ->get("/api/v1/attachments/{$attachment->id}/view")
        ->assertForbidden();
});

test('presigned url generation endpoint returns upload target', function () {
    $customer = User::factory()->customer()->create();

    $response = $this->actingAs($customer, 'api')
        ->postJson('/api/v1/attachments/presigned-url', [
            'filename' => 'diagram.png',
            'file_size' => 2 * 1024 * 1024,
            'mime_type' => 'image/png',
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'driver',
                'upload_url',
                'file_key',
                'method',
            ],
        ]);
});
