<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLA\ValueObjects;

final readonly class SlaResult
{
    public function __construct(
        public private(set) int $responseMinutes,
        public private(set) int $resolutionMinutes
    ) {}

    public function withResponseMinutes(int $minutes): self
    {
        return new self(
            responseMinutes: $minutes,
            resolutionMinutes: $this->resolutionMinutes
        );
    }

    public function withResolutionMinutes(int $minutes): self
    {
        return new self(
            responseMinutes: $this->responseMinutes,
            resolutionMinutes: $minutes,
        );
    }
}
