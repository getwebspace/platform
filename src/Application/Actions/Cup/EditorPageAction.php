<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;

class EditorPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $theme = $this->parameter('common_theme', 'default');
        $file = null;
        $absolute_path = null;
        $content = null;

        if (!empty($this->args['file'])) {
            $path = $this->resolveArg('file');
            $absolute_path = str_replace(['theme', 'resource'], [THEME_DIR . '/' . $theme, RESOURCE_DIR], $path);
            $absolute_path = realpath($absolute_path);

            if ($absolute_path !== false) {
                $file = \App\Domain\Models\File::info($absolute_path);
                $file['path'] = $path;
                $content = file_get_contents($absolute_path);
            }
        }

        if ($this->isPost()) {
            $content = $this->getParam('template');

            if (file_exists($absolute_path) && is_file($absolute_path) && is_writable($absolute_path)) {
                // delete file
                if ($this->getParam('save', 'save') === 'delete' && file_exists($absolute_path)) {
                    @unlink($absolute_path);

                    return $this->response->withAddedHeader('Location', '/cup/editor')->withStatus(301);
                }

                if (!file_exists($absolute_path)) {
                    @mkdir(dirname($absolute_path), 0o777, true);
                }

                @file_put_contents($absolute_path, $content);

                return $this->response->withAddedHeader('Location', '/cup/editor/' . $path)->withStatus(301);
            }
        }

        return $this->respondWithTemplate('cup/editor/index.twig', [
            'list' => $this->getFiles($theme),
            'file' => $file,
            'content' => $content,
        ]);
    }

    private function getFiles(string $theme): \Illuminate\Support\Collection
    {
        $list = collect();

        $list['resource'] = [
            'name' => 'Resource',
            'type' => 'section',
            'list' => $this->getFilesRecursion(RESOURCE_DIR)->sortBy('type'),
        ];

        if (($path = realpath(THEME_DIR . '/' . $theme)) !== false) {
            $list['theme'] = [
                'name' => 'Theme',
                'type' => 'section',
                'list' => $this->getFilesRecursion($path)->sortBy('type'),
            ];
        }

        return $list;
    }

    private function getFilesRecursion(string $path): \Illuminate\Support\Collection
    {
        $list = collect();

        foreach ((new \DirectoryIterator($path)) as $file) {
            if (!$file->isDot() && !str_starts_with($file->getFilename(), '.')) {
                if ($file->isDir()) {
                    $list = $list->merge([['name' => $file->getFilename(), 'type' => 'dir', 'list' => $this->getFilesRecursion($path . '/' . $file->getFilename())]]);
                } else {
                    $list = $list->merge([['name' => $file->getFilename(), 'type' => 'file']]);
                }
            }
        }

        return $list;
    }
}
