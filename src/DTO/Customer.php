<?php

declare(strict_types=1);

namespace mbarky\Ngenius\DTO;

final readonly class Customer
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $phone = null,
    ) {}
}
