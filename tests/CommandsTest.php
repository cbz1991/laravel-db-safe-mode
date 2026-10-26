<?php

namespace Cabuz\SafeMode\Tests;

use Illuminate\Support\Facades\DB;

class CommandsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('safe-mode.masking.attributes', [
            'email' => 'email',
            'name' => 'name',
        ]);
    }

    public function test_status_reports_that_the_guard_is_engaged(): void
    {
        $this->artisan('safe-mode:status')
            ->expectsOutputToContain('ENGAGED')
            ->assertSuccessful();
    }

    public function test_anonymize_rewrites_the_configured_columns(): void
    {
        $this->insertUsers('real.person@gmail.com');
        $id = DB::table('users')->value('id');

        $this->artisan('safe-mode:anonymize', ['--force' => true])->assertSuccessful();

        $row = DB::table('users')->where('id', $id)->first();

        $this->assertSame("user_{$id}@example.com", $row->email);
        $this->assertSame("Redacted {$id}", $row->name);
    }

    public function test_anonymize_refuses_to_run_in_production(): void
    {
        $this->insertUsers('real.person@gmail.com');

        $this->app['env'] = 'production';

        try {
            $exitCode = $this->artisan('safe-mode:anonymize', ['--force' => true])->run();
        } finally {
            // The migration rollback after each test asks for confirmation in
            // production, so switch back before it runs.
            $this->app['env'] = 'local';
        }

        $this->assertSame(1, $exitCode);
        $this->assertSame('real.person@gmail.com', DB::table('users')->value('email'));
    }

    public function test_anonymize_asks_for_confirmation(): void
    {
        $this->insertUsers('real.person@gmail.com');

        $this->artisan('safe-mode:anonymize')
            ->expectsConfirmation('Proceed?', 'no')
            ->assertFailed();

        $this->assertSame('real.person@gmail.com', DB::table('users')->value('email'));
    }
}
