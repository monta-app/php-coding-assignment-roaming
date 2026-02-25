<?php

namespace App\Dtos;

class OcpiResponse
{
    public function __construct(
        public readonly array $data,
        public readonly int $statusCode,
        public readonly string $statusMessage,
        public readonly string $timestamp,
        public readonly ?string $linkHeader = null,
        public readonly ?int $totalCount = null,
        public readonly ?int $limit = null,
    ) {
    }

    public static function fromHttpResponse(array $body, array $headers): self
    {
        return new self(
            data: $body['data'] ?? [],
            statusCode: $body['status_code'] ?? 0,
            statusMessage: $body['status_message'] ?? '',
            timestamp: $body['timestamp'] ?? '',
            linkHeader: $headers['Link'][0] ?? null,
            totalCount: isset($headers['X-Total-Count'][0]) ? (int)$headers['X-Total-Count'][0] : null,
            limit: isset($headers['X-Limit'][0]) ? (int)$headers['X-Limit'][0] : null,
        );
    }
}
