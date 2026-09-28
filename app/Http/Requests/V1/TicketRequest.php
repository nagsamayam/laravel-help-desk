<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class TicketRequest extends FormRequest
{
    public function idempotencyKey(): string
    {
        return trim($this->header('Idempotency-Key', ''));
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $key = $this->idempotencyKey();

                if ($key === '') {
                    $validator->errors()->add(
                        'Idempotency-Key',
                        'The Idempotency-Key header is required.'
                    );

                    return;
                }

                if (mb_strlen($key) < 16 || mb_strlen($key) > 255) {
                    $validator->errors()->add(
                        'Idempotency-Key',
                        'The Idempotency-Key header must be between 16 and 255 characters.'
                    );
                }

                if (! preg_match('/^[\x21-\x7E]+$/', $key)) {
                    $validator->errors()->add(
                        'Idempotency-Key',
                        'The Idempotency-Key header contains invalid characters.'
                    );
                }
            },
        ];
    }
}
