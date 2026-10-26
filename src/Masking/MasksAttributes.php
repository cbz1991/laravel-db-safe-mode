<?php

namespace Cabuz\SafeMode\Masking;

use Cabuz\SafeMode\Support\SafeMode;

/**
 * Add to an Eloquent model to mask PII on read while the guard is engaged.
 *
 * The real value is kept privately and restored before any write, so a stray
 * $model->save() can never persist a fake address back to the database.
 *
 * Define the map per-model:
 *
 *     protected array $maskedAttributes = ['email' => 'email', 'phone' => 'phone'];
 *
 * or leave it empty to inherit safe-mode.masking.attributes from config.
 */
trait MasksAttributes
{
    /**
     * Original values, keyed by attribute, captured before masking.
     *
     * @var array<string, mixed>
     */
    protected array $safeModeOriginals = [];

    public static function bootMasksAttributes(): void
    {
        static::retrieved(function ($model) {
            $model->applySafeModeMasks();
        });

        // Restore the real values just before they are written out, then
        // re-apply the mask afterwards so the in-memory object stays masked.
        static::saving(function ($model) {
            $model->restoreSafeModeOriginals();
        });

        static::saved(function ($model) {
            $model->applySafeModeMasks();
        });
    }

    /**
     * The model's own $maskedAttributes, if it declares any, else the config.
     *
     * @return array<string, string|\Closure>
     */
    protected function safeModeMaskMap(): array
    {
        // Optional property: models may declare $maskedAttributes or rely on config.
        $map = (array) (get_object_vars($this)['maskedAttributes'] ?? []);

        return $map !== [] ? $map : (array) config('safe-mode.masking.attributes', []);
    }

    public function applySafeModeMasks(): void
    {
        $guard = app(SafeMode::class);

        if (! $guard->isActive() || ! config('safe-mode.masking.enabled', true)) {
            return;
        }

        $maskers = app(Maskers::class);

        foreach ($this->safeModeMaskMap() as $attribute => $masker) {
            if (! array_key_exists($attribute, $this->attributes)) {
                continue;
            }

            $this->safeModeOriginals[$attribute] = $this->attributes[$attribute];

            $this->attributes[$attribute] = $maskers->apply(
                $masker,
                $this->attributes[$attribute],
                $this
            );
        }

        // Masking must not make the model look dirty.
        $this->syncOriginal();
    }

    public function restoreSafeModeOriginals(): void
    {
        foreach ($this->safeModeOriginals as $attribute => $value) {
            // Only restore if the user hasn't deliberately changed it to
            // something other than the mask we injected.
            if (($this->attributes[$attribute] ?? null) === $this->getOriginal($attribute)) {
                $this->attributes[$attribute] = $value;
            }
        }
    }
}
