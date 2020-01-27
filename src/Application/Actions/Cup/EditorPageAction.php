<?php

namespace App\Application\Actions\Cup;

use App\Application\Actions\Action;
use DirectoryIterator;

class EditorPageAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect();
        $file = null;
        $content = null;

        if (($path = realpath(THEME_DIR . '/' . $this->getParameter('common_theme'))) !== false) {
            $list = $this->getCatalog($path)->sortBy('type');
        }

        if (isset($this->args['file']) && ($path = realpath(THEME_DIR . '/' . $this->getParameter('common_theme') . '/' . $this->resolveArg('file'))) !== false) {
            $file = \App\Domain\Entities\File::info($path);
            $content = file_get_contents($path);
        }

        if ($this->request->isPost()) {
            $data = $this->request->getParam('content');
        }

        return $this->respondRender('cup/editor/index.twig', ['list' => $list, 'file' => $file, 'content' => $content]);
    }

    private function getCatalog($path)
    {
        $list = collect();

        foreach ((new DirectoryIterator($path)) as $file) {
            if (!$file->isDot() && !str_starts_with('.', $file->getFilename())) {
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
