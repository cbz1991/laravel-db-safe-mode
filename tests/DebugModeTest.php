<?php

namespace Cabuz\SafeMode\Tests;

use Cabuz\SafeMode\Exceptions\SchedulerHaltedException;
use Cabuz\SafeMode\SafeModeServiceProvider;
use Cabuz\SafeMode\Support\SafeMode;
use Cabuz\SafeMode\Tests\Fixtures\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Debugging on a production clone: jobs must be able to write and run, but
 * nothing may reach real people.
 */
class DebugModeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('safe-mode.mode', 'debug');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.mailers.array', ['transport' => 'array']);
    }

    public function test_the_mode_is_debug(): void
    {
        $guard = $this->app->make(SafeMode::class);

        $this->assertSame('debug', $guard->mode());
        $this->assertTrue($guard->isExplicitMode());
    }

    public function test_the_banner_says_writes_are_allowed(): void
    {
        $banner = SafeModeServiceProvider::bannerFor($this->app->make(SafeMode::class));

        $this->assertStringContainsString('DEBUG MODE', $banner);
        $this->assertStringContainsString('writes ALLOWED', $banner);
        $this->assertStringContainsString('scheduler blocked', $banner);
    }

    public function test_status_shows_the_mode(): void
    {
        $this->artisan('safe-mode:status')
            ->expectsOutputToContain('ENGAGED in debug mode')
            ->assertSuccessful();
    }

    public function test_jobs_can_write(): void
    {
        $this->insertUsers('real.person@gmail.com');

        // The renew-job scenario: a flag gets set on a real user.
        DB::table('users')->update(['is_premium' => true]);

        $this->assertTrue((bool) DB::table('users')->value('is_premium'));
    }

    public function test_models_can_be_created(): void
    {
        User::query()->create(['name' => 'New Person', 'email' => 'new.person@gmail.com']);

        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_queue_workers_started_by_hand_run(): void
    {
        $exitCode = Artisan::call('queue:work', ['--once' => true, '--sleep' => 0]);

        $this->assertSame(0, $exitCode);
    }

    public function test_the_scheduler_stays_off(): void
    {
        $ran = false;

        $this->app->make(Schedule::class)->call(function () use (&$ran) {
            $ran = true;
        })->everyMinute();

        try {
            Artisan::call('schedule:run');

            $this->fail('schedule:run was not halted.');
        } catch (SchedulerHaltedException) {
            // Expected.
        }

        $this->assertFalse($ran);
    }

    public function test_mail_still_does_not_reach_real_people(): void
    {
        Mail::raw('Your subscription renews tomorrow', function ($message) {
            $message->to('real.person@gmail.com')->subject('Renewal');
        });

        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
