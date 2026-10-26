<?php

namespace Cabuz\SafeMode\Tests;

use Cabuz\SafeMode\Exceptions\SchedulerHaltedException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Artisan;

class QueueAndSchedulerTest extends TestCase
{
    public function test_the_queue_worker_is_paused(): void
    {
        // queue:work stops pulling jobs when a Looping listener returns false.
        $this->assertFalse($this->app['events']->until(new Looping('database', 'default')));
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
