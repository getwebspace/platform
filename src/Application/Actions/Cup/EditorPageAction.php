<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use DirectoryIterator;

class EditorPageAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect();
        $file = null;
        $content = null;
        $theme_dir = THEME_DIR . '/' . $this->parameter('common_theme', 'default');

        if (($path = realpath($theme_dir)) !== false) {
            $list = $this->getCatalog($path)->sortBy('type');
        }

        if (isset($this->args['file']) && ($path = realpath($theme_dir . '/' . $this->resolveArg('file'))) !== false) {
            $file = \App\Domain\Entities\File::info($path);
            $file['path'] = str_replace($theme_dir . '/', '', $file['dir'] . '/' . $file['name'] . '.' . $file['ext']);
            $content = file_get_contents($path);
        }

        if ($this->request->isPost()) {
            $path = str_replace('..', '', $this->request->getParam('path'));
            $absolute_path = $theme_dir . '/' . $path;
            $content = $this->request->getParam('content');

            // удаление файла
            if ($this->request->getParam('save', 'exit') === 'delete' && file_exists($absolute_path)) {
                unlink($absolute_path);

                return $this->response->withAddedHeader('Location', '/cup/editor')->withStatus(301);
            }

            if (!file_exists($absolute_path)) {
                mkdir(dirname($absolute_path), 0777, true);
            }

            file_put_contents($absolute_path, $content);

            return $this->response->withAddedHeader('Location', '/cup/editor/' . $path)->withStatus(301);
        }

        return $this->respondWithTemplate('cup/editor/index.twig', ['list' => $list, 'file' => $file, 'content' => $content]);
    }

    private function getCatalog($path)
    {
        $list = collect();

        foreach ((new DirectoryIterator($path)) as $file) {
            if (!$file->isDot() && !str_starts_with($file->getFilename(), '.')) {
                if ($file->isDir()) {
                    $list = $list->merge([['name' => $file->getFilename(), 'type' => 'dir', 'list' => $this->getCatalog($path . '/' . $file->getFilename())]]);
                } else {
                    $list = $list->merge([['name' => $file->getFilename(), 'type' => 'file']]);
                }
            }
        }

        return $list;
    }
}
