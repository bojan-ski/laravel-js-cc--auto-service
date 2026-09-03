<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class ThrowingMailTransport implements TransportInterface
{
    public const MESSAGE = 'the mail server refused the message';

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        throw new RuntimeException(self::MESSAGE);
    }

    public function __toString(): string
    {
        return 'throwing';
    }
}
