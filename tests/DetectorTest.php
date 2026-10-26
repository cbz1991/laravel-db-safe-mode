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

    public function test_a_missing_table_is_not_real_data(): void
    {
        config(['safe-mode.detect_real_data.table' => 'does_not_exist']);

        $this->assertFalse($this->detector()->hasRealData());
    }
}
