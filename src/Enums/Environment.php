<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Enums;

enum Environment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public static function fromConfig(): self
    {
        $raw = config('ngenius.environment', 'sandbox');

        return self::from(is_string($raw) ? $raw : 'sandbox');
    }

    public function baseUrl(): string
    {
        $raw = config("ngenius.{$this->value}.base_url");

        return is_string($raw) ? $raw : '';
    }

    public function apiKey(): string
    {
        $raw = config("ngenius.{$this->value}.api_key");

        return is_string($raw) ? $raw : '';
    }

    public function outletReference(): string
    {
        $raw = config("ngenius.{$this->value}.outlet_reference");

        return is_string($raw) ? $raw : '';
    }
}
