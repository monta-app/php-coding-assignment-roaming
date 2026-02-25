<?php

namespace App\Dtos;

class OcpiClientHeaders
{
    public function __construct(
        public readonly string $token,
    ) {
    }

    public function toArray(): array
    {
        return [
            'Authorization' => 'Token ' . $this->token,
        ];
    }
}
