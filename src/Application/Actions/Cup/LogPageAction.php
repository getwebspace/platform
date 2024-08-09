<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;

class LogPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $files = [];

        foreach (glob(LOG_DIR . '/app-*.log') as $path) {
            if (file_exists($path)) {
                $files[basename($path)] = $this->getFileContents($path);
            }
        }

        return $this->respondWithTemplate('cup/logs.twig', [
            'files' => array_reverse($files),
        ]);
    }

    private function getFileContents($path, $lines = 1000): string
    {
        $file = file($path);

        return implode(PHP_EOL, array_map('trim', array_slice($file, 0 - $lines)));
    }
}
