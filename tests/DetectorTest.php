<?php

namespace Cabuz\SafeMode\Tests;

use Cabuz\SafeMode\Support\Detector;

class DetectorTest extends TestCase
{
    protected function detector(): Detector
    {
        return new Detector($this->app, config('safe-mode'));
    }

    public function test_an_empty_table_is_not_real_data(): void
    {
        $this->assertFalse($this->detector()->hasRealData());
    }

    public function test_faker_safe_email_domains_are_not_real_data(): void
    {
        $this->insertUsers('a@example.com', 'b@example.org', 'c@example.net');

        $this->assertFalse($this->detector()->hasRealData());
    }

    public function test_a_single_real_email_is_real_data(): void
    {
        $this->insertUsers('a@example.com', 'real.person@gmail.com');

        $this->assertTrue($this->detector()->hasRealData());
    }

    public function test_custom_anonymized_domains(): void
    {
        config(['safe-mode.detect_real_data.anonymized_domains' => ['acme.test']]);

        $this->insertUsers('a@acme.test');
        $this->assertFalse($this->detector()->hasRealData());

        $this->insertUsers('b@example.com');
        $this->assertTrue($this->detector()->hasRealData());
    }

    /** Configs published before 1.2.3 have "anonymized_suffix" and no domain list. */
    public function test_the_legacy_anonymized_suffix_setting_is_still_honoured(): void
    {
        config(['safe-mode.detect_real_data' => [
            'enabled' => true,
            'table' => 'users',
            'column' => 'email',
            'anonymized_suffix' => '@legacy.test',
        ]]);

        $this->insertUsers('a@legacy.test', 'b@example.com');

        $this->assertFalse($this->detector()->hasRealData());
    }

    public function test_without_safe_mode_the_mode_follows_the_data(): void
    {
        config(['safe-mode.mode' => null]);

        $this->assertSame('strict', $this->detector()->mode(realData: true));
        $this->assertSame('debug', $this->detector()->mode(realData: false));
        $this->assertFalse($this->detector()->modeIsExplicit());
    }

    public function test_safe_mode_overrides_the_data(): void
    {
        config(['safe-mode.mode' => 'debug']);
        $this->assertSame('debug', $this->detector()->mode(realData: true));

        config(['safe-mode.mode' => 'STRICT']);
        $this->assertSame('strict', $this->detector()->mode(realData: false));
        $this->assertTrue($this->detector()->modeIsExplicit());
    }

    public function test_an_unknown_mode_falls_back_to_strict(): void
    {
        config(['safe-mode.mode' => 'yolo']);

        $this->assertSame('strict', $this->detector()->mode(realData: false));
    }

    public function test_mode_settings_can_be_customised(): void
    {
        config(['safe-mode.modes.debug.halt_scheduler' => false]);

        $this->assertSame(
            ['block_writes' => false, 'halt_queue' => false, 'halt_scheduler' => false],
            $this->detector()->modeSettings('debug')
        );
        $this->assertSame(
            ['block_writes' => true, 'halt_queue' => true, 'halt_scheduler' => true],
            $this->detector()->modeSettings('strict')
        );
    }

    /** Older published configs have global switches and no "modes" key. */
    public function test_legacy_global_switches_still_turn_guards_off(): void
    {
        config([
            'safe-mode.modes' => null,
            'safe-mode.writes.block' => false,
            'safe-mode.queue' => ['halt' => false],
            'safe-mode.scheduler' => ['halt' => true],
        ]);

        $this->assertSame(
            ['block_writes' => false, 'halt_queue' => false, 'halt_scheduler' => true],
            $this->detector()->modeSettings('strict')
        );
    }

    public function test_a_missing_table_is_not_real_data(): void
    {
        config(['safe-mode.detect_real_data.table' => 'does_not_exist']);

        $this->assertFalse($this->detector()->hasRealData());
    }
}
