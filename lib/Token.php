<?php

declare(strict_types=1);

namespace KLXM\Dav;

use DateTimeImmutable;

/**
 * Ein App-Passwort. Gespeichert ist nur der Hash.
 */
final readonly class Token
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $label,
        public Scope $scope,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {}

    public function isExpired(DateTimeImmutable $now): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= $now;
    }
}
