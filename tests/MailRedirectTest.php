<?php

namespace Cabuz\SafeMode\Tests;

use Illuminate\Support\Facades\Mail;

class MailRedirectTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('safe-mode.outbound.mail.redirect_to', 'dev@example.test');

        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.mailers.array', ['transport' => 'array']);
        $app['config']->set('mail.mailers.secondary', ['transport' => 'array']);
    }

    /** @return list<string> */
    protected function recipientsSentThrough(string $mailer): array
    {
        $messages = Mail::mailer($mailer)->getSymfonyTransport()->messages();

        $this->assertCount(1, $messages);

        return array_map(
            fn ($address) => $address->getAddress(),
            $messages->first()->getEnvelope()->getRecipients()
        );
    }

    public function test_mail_is_redirected_and_cc_bcc_are_dropped(): void
    {
        Mail::raw('Hello', function ($message) {
            $message->to('real.person@gmail.com')
                ->cc('cc.person@gmail.com')
                ->bcc('bcc.person@gmail.com')
                ->subject('Hi');
        });

        $this->assertSame(['dev@example.test'], $this->recipientsSentThrough('array'));

        $original = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

        $this->assertSame(
            'real.person@gmail.com, cc.person@gmail.com, bcc.person@gmail.com',
            $original->getHeaders()->getHeaderBody('X-Safe-Mode-Original-Recipients')
        );
    }

    public function test_mail_through_a_non_default_mailer_is_redirected_too(): void
    {
        Mail::mailer('secondary')->raw('Hello', function ($message) {
            $message->to('real.person@gmail.com')->subject('Hi');
        });

        $this->assertSame(['dev@example.test'], $this->recipientsSentThrough('secondary'));
    }
}
