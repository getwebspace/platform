<?php declare(strict_types=1);

namespace App\Application\Mail;

use App\Application\Mail\Exception\MailException;

interface MailProviderInterface
{
    /**
     * Hand the message to the transport
     *
     * A return means the transport accepted it - there is no false/null
     * "maybe it worked" outcome. Anything that goes wrong (can't connect,
     * auth rejected, no valid recipient, ...) is a MailException instead,
     * carrying the real reason, so a caller can never mistake a failure for
     * success just by forgetting to check a boolean
     *
     * @throws MailException
     */
    public static function send(array $data = []): void;
}
