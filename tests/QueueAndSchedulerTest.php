<?php

namespace Cabuz\SafeMode\Tests;

use Cabuz\SafeMode\Exceptions\QueueHaltedException;
use Cabuz\SafeMode\Exceptions\SchedulerHaltedException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/** Strict mode (the TestCase default). */
class QueueAndSchedulerTest extends TestCase
{
    public function test_queue_workers_are_refused(): void
    {
        $this->expectException(QueueHaltedException::class);

        // --once never enters the worker loop, so it must be stopped up front.
        Artisan::call('queue:work', ['--once' => true, '--sleep' => 0]);
    }

    public function test_scheduled_tasks_do_not_run(): void
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
}
