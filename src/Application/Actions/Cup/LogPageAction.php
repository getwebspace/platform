<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use DirectoryIterator;

class LogPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/logs.twig', [
            'files' => [
                'app.log' => $this->getFileContents('app.log'),
                'error.log' => $this->getFileContents('error.log'),
                'exception.log' => $this->getFileContents('exception.log'),
                'nginx.access.log' => $this->getFileContents('nginx.access.log'),
                'nginx.error.log' => $this->getFileContents('nginx.error.log'),
            ]
        ]);
    }

    protected function getFileContents($filename, $lines = 500): string
    {
        if (file_exists(LOG_DIR . '/' . $filename)) {
            $file = file(LOG_DIR . '/' . $filename);

            if ($file) {
                return implode(PHP_EOL, array_map('trim', array_slice($file, 0 - $lines)));
            }
        }

        return '';
    }
}
