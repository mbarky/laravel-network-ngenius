<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Client\Concerns;

use mbarky\Ngenius\Exceptions\NgeniusException;

/**
 * Centralises HAL/HATEOAS link extraction from N-Genius responses.
 *
 * N-Genius returns HATEOAS _links on every response. All follow-up URLs must
 * be extracted here — never hand-build endpoint paths outside this trait.
 */
trait HasHalLinks
{
    /**
     * Extract a named link href from the _links block.
     *
     * @throws NgeniusException if the link is not present
     */
    public function link(string $name): string
    {
        $href = data_get($this->body, "_links.{$name}.href");

        if (! is_string($href) || $href === '') {
            throw new NgeniusException(
                "HAL link '{$name}' not found in N-Genius response."
            );
        }

        return $href;
    }

    /** Returns the link href or null when absent (non-throwing variant). */
    public function linkOrNull(string $name): ?string
    {
        $href = data_get($this->body, "_links.{$name}.href");

        return is_string($href) && $href !== '' ? $href : null;
    }

    /** True when the named link exists in the response. */
    public function hasLink(string $name): bool
    {
        return $this->linkOrNull($name) !== null;
    }
}
