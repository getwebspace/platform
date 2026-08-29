<?php declare(strict_types=1);

namespace App\Application\Mail\Exception;

/**
 * A provider failed to hand the message to the transport
 *
 * The message is the provider's own human-readable reason (PHPMailer's
 * `ErrorInfo` for SMTP - "SMTP connect() failed...", "Could not
 * authenticate.", "Invalid address: ...") - safe to log or store, it never
 * contains credentials, only what specifically went wrong
 */
class MailException extends \RuntimeException {}
