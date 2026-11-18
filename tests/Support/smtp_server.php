<?php declare(strict_types=1);

/**
 * A just-enough SMTP server for the mail tests: accepts any login, takes any
 * message and appends it, as one JSON line, to the given file
 *
 *   php smtp_server.php <port> <outfile>
 *
 * Stops when <outfile>.stop appears or after a minute, whichever is first
 */
[, $port, $out] = $argv;

$server = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $error);
if (!$server) {
    fwrite(STDERR, "cannot listen: {$error}\n");
    exit(1);
}

$deadline = time() + 60;

while (time() < $deadline && !file_exists($out . '.stop')) {
    $read = [$server];
    $write = $except = null;
    if (stream_select($read, $write, $except, 0, 200000) < 1) {
        continue;
    }

    $client = @stream_socket_accept($server, 1);
    if (!$client) {
        continue;
    }

    $say = static fn (string $line) => fwrite($client, $line . "\r\n");
    $message = ['from' => '', 'to' => [], 'data' => ''];
    $inData = false;

    $say('220 localhost ESMTP');

    while (($line = fgets($client)) !== false) {
        if ($inData) {
            if (rtrim($line, "\r\n") === '.') {
                $inData = false;
                file_put_contents($out, json_encode($message) . "\n", FILE_APPEND | LOCK_EX);
                $message = ['from' => '', 'to' => [], 'data' => ''];
                $say('250 OK queued');
            } else {
                $message['data'] .= $line;
            }

            continue;
        }

        $command = strtoupper(trim($line));

        switch (true) {
            case str_starts_with($command, 'EHLO'):
            case str_starts_with($command, 'HELO'):
                $say('250-localhost');
                $say('250 AUTH LOGIN PLAIN');

                break;

            case str_starts_with($command, 'AUTH LOGIN'):
                $say('334 VXNlcm5hbWU6');
                fgets($client);
                $say('334 UGFzc3dvcmQ6');
                fgets($client);
                $say('235 Authentication successful');

                break;

            case str_starts_with($command, 'AUTH'):
                $say('235 Authentication successful');

                break;

            case str_starts_with($command, 'MAIL FROM'):
                preg_match('/<([^>]*)>/', $line, $m);
                $message['from'] = $m[1] ?? '';
                $say('250 OK');

                break;

            case str_starts_with($command, 'RCPT TO'):
                preg_match('/<([^>]*)>/', $line, $m);
                $message['to'][] = $m[1] ?? '';
                $say('250 OK');

                break;

            case $command === 'DATA':
                $inData = true;
                $say('354 End data with <CR><LF>.<CR><LF>');

                break;

            case $command === 'QUIT':
                $say('221 Bye');
                break 2;

            default:
                $say('250 OK');
        }
    }

    fclose($client);
}
