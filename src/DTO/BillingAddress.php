<?php

declare(strict_types=1);

namespace mbarky\Ngenius\DTO;

final readonly class BillingAddress
{
    public function __construct(
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $address1 = null,
        public readonly ?string $city = null,
        public readonly ?string $countryCode = null,
    ) {}
}
