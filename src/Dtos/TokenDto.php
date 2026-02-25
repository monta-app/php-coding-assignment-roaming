<?php

namespace App\Dtos;

class TokenDto
{
    public function __construct(
        public readonly string $uid,
        public readonly TokenType $type,
        public readonly string $authId,
        public readonly string $issuer,
        public readonly bool $valid,
        public readonly WhitelistType $whitelist,
        public readonly string $lastUpdated,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            uid: $data['uid'],
            type: TokenType::from($data['type']),
            authId: $data['auth_id'],
            issuer: $data['issuer'],
            valid: $data['valid'],
            whitelist: WhitelistType::from($data['whitelist']),
            lastUpdated: $data['last_updated'],
        );
    }
}
