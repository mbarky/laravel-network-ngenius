<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Client;

use mbarky\Ngenius\Client\Concerns\HasHalLinks;
use mbarky\Ngenius\Exceptions\NgeniusException;

/**
 * Wraps a raw N-Genius API response and provides typed access to its body.
 */
final class NgeniusResponse
{
    use HasHalLinks;

    /** @param array<mixed, mixed> $body */
    public function __construct(
        private readonly array $body,
        private readonly int $status,
    ) {}

    /** @param array<mixed, mixed> $body */
    public static function fromArray(array $body, int $status): self
    {
        return new self($body, $status);
    }

    /** @return array<mixed, mixed> */
    public function body(): array
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** @throws NgeniusException */
    public function throw(): static
    {
        if (! $this->successful()) {
            throw new NgeniusException(
                "N-Genius API error {$this->status}: ".json_encode($this->body),
                $this->status
            );
        }

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->body, $key, $default);
    }
}
