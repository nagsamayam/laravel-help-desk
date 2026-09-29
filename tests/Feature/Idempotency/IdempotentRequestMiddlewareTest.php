<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Infrastructure\Idempotency\IdempotencyResource;
use App\Infrastructure\Idempotency\IdempotencyResponse;
use App\Infrastructure\Idempotency\Models\IdempotencyKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::post('/test-idempotent-resource', function (): IdempotencyResponse {
        return new IdempotencyResponse(
            data: ['result' => 'created'],
            status: 201,
            resource: new IdempotencyResource('ticket', 123),
        );
    })->middleware(['idempotent']);

    Route::post('/test-idempotent-default', function (Request $request): JsonResponse {
        return response()->json([
            'result' => 'created',
            'value' => $request->input('value'),
        ], 201)->header('X-Custom-Header', 'custom-value');
    })->middleware(['idempotent']);

    Route::post('/test-idempotent-enforced', function (Request $request): JsonResponse {
        return response()->json(['result' => 'ok'], 200);
    })->middleware(['idempotent:user,null,required']);

    Route::get('/test-idempotent-safe-get', function (): JsonResponse {
        return response()->json(['status' => 'healthy']);
    })->middleware(['idempotent']);

    Route::put('/test-idempotent-custom/{id}', function (Request $request, string $id): JsonResponse {
        return response()->json([
            'id' => $id,
            'updated' => true,
        ], 200);
    })->middleware(['idempotent:tenant,custom.op']);
});

it('passes through safe GET requests without requiring or recording idempotency', function (): void {
    $response = $this->getJson('/test-idempotent-safe-get');

    $response->assertOk()
        ->assertJson(['status' => 'healthy'])
        ->assertHeaderMissing('Idempotency-Replayed');
});

it('executes request and replays response via middleware', function (): void {
    $user = User::factory()->create();
    $key = 'test-middleware-key-12345';

    $payload = ['value' => 'first-payload'];

    $firstResponse = $this->actingAs($user, 'api')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/test-idempotent-default', $payload);

    $firstResponse->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'false')
        ->assertHeader('X-Custom-Header', 'custom-value')
        ->assertJson(['result' => 'created', 'value' => 'first-payload']);

    $secondResponse = $this->actingAs($user, 'api')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/test-idempotent-default', $payload);

    $secondResponse->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertHeader('X-Custom-Header', 'custom-value')
        ->assertExactJson($firstResponse->json());
});

it('returns 409 conflict when idempotency key is reused with different payload', function (): void {
    $user = User::factory()->create();
    $key = 'test-middleware-conflict-12345';

    $this->actingAs($user, 'api')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/test-idempotent-default', ['value' => 'initial-payload'])
        ->assertCreated();

    $conflictResponse = $this->actingAs($user, 'api')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/test-idempotent-default', ['value' => 'different-payload']);

    $conflictResponse->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
});

it('validates idempotency key length and characters', function (): void {
    $user = User::factory()->create();

    // Too short (< 16 chars)
    $responseShort = $this->actingAs($user, 'api')
        ->withHeader('Idempotency-Key', 'short')
        ->postJson('/test-idempotent-default', ['value' => 'test']);

    $responseShort->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.Idempotency-Key.0', 'The Idempotency-Key header must be between 16 and 255 characters.');

    // Invalid characters (spaces/newlines)
    $responseInvalidChars = $this->actingAs($user, 'api')
        ->withHeader('Idempotency-Key', "invalid key \n with whitespace")
        ->postJson('/test-idempotent-default', ['value' => 'test']);

    $responseInvalidChars->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.Idempotency-Key.0', 'The Idempotency-Key header contains invalid characters.');
});

it('requires idempotency key when enforce parameter is set', function (): void {
    $response = $this->postJson('/test-idempotent-enforced', []);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.Idempotency-Key.0', 'The Idempotency-Key header is required.');
});

it('allows requests without idempotency key when enforce is optional', function (): void {
    $response = $this->postJson('/test-idempotent-default', ['value' => 'no-key']);

    $response->assertCreated()
        ->assertJson(['result' => 'created', 'value' => 'no-key'])
        ->assertHeaderMissing('Idempotency-Replayed');
});

it('supports custom scope and operation with route parameters', function (): void {
    $key = 'test-custom-scope-key-12345';

    $first = $this->withHeader('Idempotency-Key', $key)
        ->putJson('/test-idempotent-custom/item-42', ['data' => 'abc']);

    $first->assertOk()
        ->assertHeader('Idempotency-Replayed', 'false')
        ->assertJson(['id' => 'item-42', 'updated' => true]);

    $second = $this->withHeader('Idempotency-Key', $key)
        ->putJson('/test-idempotent-custom/item-42', ['data' => 'abc']);

    $second->assertOk()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertJson(['id' => 'item-42', 'updated' => true]);
});

it('persists resource metadata from the idempotency response', function (): void {
    $user = User::factory()->create();
    $key = 'resource-metadata-key-123';

    $this->actingAs($user, 'api')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/test-idempotent-resource')
        ->assertCreated();

    $record = IdempotencyKey::query()->firstOrFail();

    expect($record->resource_type)->toBe('ticket')
        ->and($record->resource_id)->toBe('123');
});
