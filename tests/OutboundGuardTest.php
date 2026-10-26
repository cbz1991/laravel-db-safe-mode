<?php

namespace Cabuz\SafeMode\Tests;

use Cabuz\SafeMode\Tests\Fixtures\User;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class OutboundGuardTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.mailers.array', ['transport' => 'array']);
    }

    public function test_mail_is_not_sent_when_no_redirect_address_is_set(): void
    {
        Mail::raw('Hello', function ($message) {
            $message->to('real.person@gmail.com')->subject('Hi');
        });

        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_blocked_notification_channels_are_stopped(): void
    {
        $result = $this->app['events']->until(
            new NotificationSending(new User, new class extends Notification {}, 'vonage')
        );

        $this->assertFalse($result);
    }

    public function test_allowed_notification_channels_do_not_stop_other_listeners(): void
    {
        $called = false;

        Event::listen(NotificationSending::class, function () use (&$called) {
            $called = true;
        });

        $result = $this->app['events']->until(
            new NotificationSending(new User, new class extends Notification {}, 'database')
        );

        $this->assertNull($result);
        $this->assertTrue($called, "The application's own NotificationSending listener did not run.");
    }
}
