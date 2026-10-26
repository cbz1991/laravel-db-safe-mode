<?php

namespace Cabuz\SafeMode\Guards;

use Cabuz\SafeMode\Exceptions\OutboundBlockedException;
use Cabuz\SafeMode\Exceptions\SchedulerHaltedException;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Queue\Events\Looping;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Stops every side effect that could reach the outside world while real data
 * is in use. Each guard is independently toggled through config.
 */
class OutboundGuard
{
    public function __construct(
        protected Application $app,
        protected array $config,
    ) {}

    public function register(): void
    {
        if (data_get($this->config, 'mail.block', true)) {
            $this->guardMail();
        }

        if (data_get($this->config, 'notifications.block', true)) {
            $this->guardNotifications();
        }

        // Laravel 10 only suggests Guzzle. Without it the app can't make
        // Http:: calls anyway, so there is nothing to guard.
        if (data_get($this->config, 'http.block', true) && class_exists(\GuzzleHttp\Middleware::class)) {
            $this->guardHttp();
        }
    }

    public function registerQueueHalt(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app['events'];

        // Pauses the daemon worker (queue:work) before it pulls a job.
        $events->listen(Looping::class, fn () => false);
    }

    public function registerSchedulerHalt(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app['events'];

        $events->listen(ScheduledTaskStarting::class, function () {
            throw SchedulerHaltedException::make();
        });
    }

    /**
     * MessageSending fires for every mailer, right before the transport sends,
     * so this covers Mail::mailer('ses') and friends, not just the default.
     */
    protected function guardMail(): void
    {
        $redirectTo = data_get($this->config, 'mail.redirect_to');

        /** @var Dispatcher $events */
        $events = $this->app['events'];

        $events->listen(MessageSending::class, function (MessageSending $event) use ($redirectTo) {
            $message = $event->message;

            if (! $message instanceof Email) {
                return false;
            }

            $recipients = array_map(
                fn (Address $address) => $address->getAddress(),
                [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()]
            );

            if (! $redirectTo) {
                $this->app['log']->info('[safe-mode] Blocked an outgoing email.', [
                    'recipients' => $recipients,
                    'subject' => $message->getSubject(),
                ]);

                // Returning false from an `until` listener cancels the send.
                return false;
            }

            // Every message, whatever its To/Cc/Bcc, lands in one inbox.
            $headers = $message->getHeaders();
            $headers->remove('Cc');
            $headers->remove('Bcc');
            $headers->remove('X-Safe-Mode-Original-Recipients');
            $headers->addTextHeader('X-Safe-Mode-Original-Recipients', implode(', ', $recipients));

            $message->to($redirectTo);

            // Return nothing, so the application's own listeners still run.
            return null;
        });
    }

    protected function guardNotifications(): void
    {
        $allowed = (array) data_get($this->config, 'notifications.allowed_channels', ['mail', 'database']);

        /** @var Dispatcher $events */
        $events = $this->app['events'];

        // Returning false from an `until` listener halts the send for that
        // channel. Allowed channels must return null, not true: any non-null
        // response stops the remaining listeners from running.
        $events->listen(NotificationSending::class, function (NotificationSending $event) use ($allowed) {
            return in_array($event->channel, $allowed, true) ? null : false;
        });
    }

    protected function guardHttp(): void
    {
        $allowedHosts = (array) data_get($this->config, 'http.allowed_hosts', []);

        /** @var HttpFactory $http */
        $http = $this->app->make(HttpFactory::class);

        $http->globalRequestMiddleware(function ($request) use ($allowedHosts) {
            $host = $request->getUri()->getHost();

            foreach ($allowedHosts as $allowed) {
                if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                    return $request;
                }
            }

            throw OutboundBlockedException::forHost($host);
        });
    }
}
