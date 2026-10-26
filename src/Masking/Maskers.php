<?php

namespace Cabuz\SafeMode\Masking;

use Closure;

/**
 * Registry of named masking strategies. Register your own with
 * Maskers::extend('name', fn ($value, $model) => ...).
 */
class Maskers
{
    /** @var array<string, Closure> */
    protected array $maskers = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    public function extend(string $name, Closure $masker): void
    {
        $this->maskers[$name] = $masker;
    }

    public function apply(string|Closure $masker, mixed $value, object $model): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($masker instanceof Closure) {
            return $masker($value, $model);
        }

        if (! isset($this->maskers[$masker])) {
            return $value;
        }

        return ($this->maskers[$masker])($value, $model);
    }

    protected function registerDefaults(): void
    {
        // Stable per-row fake address derived from the primary key.
        $this->maskers['email'] = function ($value, $model) {
            $key = $model->getKey() ?? substr(md5((string) $value), 0, 8);

            return "user_{$key}@example.com";
        };

        $this->maskers['phone'] = fn () => '+10000000000';

        $this->maskers['name'] = function ($value, $model) {
            $key = $model->getKey() ?? '';

            return trim("Redacted {$key}");
        };

        // Keep the shape (length) but hide the content.
        $this->maskers['redact'] = fn ($value) => str_repeat('•', min(mb_strlen((string) $value), 12));
    }
}
