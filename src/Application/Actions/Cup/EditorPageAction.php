<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;

/**
 * File editor: the page itself (file tree + tabs, see cup/editor/index.twig) and the small JSON api it works through
 *
 *   GET  /cup/editor                       the page
 *   GET  /cup/editor/{path}?json=1         {path, content, size, writable} of one file
 *   POST /cup/editor/{path}  save=ajax     writes the file, {ok, size}
 *   POST /cup/editor/{path}  save=create   creates an empty file (and its folders), {ok}
 *   POST /cup/editor/{path}  save=delete   removes the file, {ok}
 *
 * {path} starts with a section name - "theme/..." or "resource/..." - and can never leave that section's folder
 */
class EditorPageAction extends AbstractAction
{
    /** a bigger file is not opened in the browser editor */
    private const MAX_SIZE = 2 * 1024 * 1024;

    protected function action(): \Slim\Psr7\Response
    {
        $theme = $this->parameter('common_theme', 'default');
        $path = !empty($this->args['file']) ? trim($this->resolveArg('file'), '/') : '';

        if ($this->getParam('json') || $this->isPost()) {
            return $this->api($path, $theme);
        }

        return $this->respondWithTemplate('cup/editor/index.twig', [
            'tree' => $this->getFiles($theme),
            'open' => $path,
        ]);
    }

    private function api(string $path, string $theme): \Slim\Psr7\Response
    {
        $absolute = $this->absolutePath($path, $theme);

        if ($absolute === null) {
            return $this->fail('Wrong path', 400);
        }

        if (!$this->isPost()) {
            if (!is_file($absolute)) {
                return $this->fail('File not found', 404);
            }
            if (($size = filesize($absolute)) > self::MAX_SIZE) {
                return $this->fail('File is too big to edit here', 413);
            }

            $content = (string) file_get_contents($absolute);

            // binary content (images, archives) is not editable as text
            if (str_contains($content, "\0")) {
                return $this->fail('Binary file', 415);
            }

            return $this->respondWithJson([
                'ok' => true,
                'path' => $path,
                'content' => $content,
                'size' => $size,
                'writable' => is_writable($absolute),
            ]);
        }

        switch ($this->getParam('save', 'ajax')) {
            case 'create':
                if (file_exists($absolute)) {
                    return $this->fail('File already exists', 409);
                }
                if (!is_dir(dirname($absolute)) && !@mkdir(dirname($absolute), 0o777, true)) {
                    return $this->fail('Cannot create the folder', 500);
                }
                if (@file_put_contents($absolute, '') === false) {
                    return $this->fail('Cannot create the file', 500);
                }

                return $this->respondWithJson(['ok' => true, 'path' => $path]);

            case 'delete':
                if (!is_file($absolute) || !is_writable($absolute) || !@unlink($absolute)) {
                    return $this->fail('Cannot delete the file', 400);
                }
                $this->forgetCompiledTemplates($absolute);

                return $this->respondWithJson(['ok' => true]);

            default:
                if (!is_file($absolute) || !is_writable($absolute)) {
                    return $this->fail('The file is not writable', 403);
                }
                if (@file_put_contents($absolute, (string) $this->getParam('content', '')) === false) {
                    return $this->fail('Cannot write the file', 500);
                }
                clearstatcache(true, $absolute);
                $this->forgetCompiledTemplates($absolute);

                return $this->respondWithJson(['ok' => true, 'size' => filesize($absolute)]);
        }
    }

    private function fail(string $message, int $status): \Slim\Psr7\Response
    {
        return $this->respondWithJson(['ok' => false, 'error' => $message])->withStatus($status);
    }

    /**
     * "theme/x/y.twig" -> absolute path, only inside the theme or resource folder; null for anything else
     * (the target may not exist yet, so its folder is the one that is resolved and checked)
     */
    private function absolutePath(string $path, string $theme): ?string
    {
        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        [$section, $rest] = array_pad(explode('/', $path, 2), 2, '');

        $base = match ($section) {
            'theme' => realpath(THEME_DIR . '/' . $theme),
            'resource' => realpath(RESOURCE_DIR),
            default => false,
        };

        if ($base === false || $rest === '') {
            return null;
        }

        foreach (explode('/', $rest) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return null;
            }
        }

        $target = $base . '/' . $rest;
        $real = realpath($target) ?: realpath(dirname($target));

        // an existing target, or the folder of a new one (it may not exist yet either, then its closest parent must be inside)
        if ($real === false) {
            $parent = dirname($target);

            while ($parent !== $base && !is_dir($parent) && str_starts_with($parent, $base)) {
                $parent = dirname($parent);
            }
            $real = realpath($parent);
        }

        if ($real === false || ($real !== $base && !str_starts_with($real, $base . '/'))) {
            return null;
        }

        return $target;
    }

    /**
     * A working site does not compile templates on the fly: a changed template is not seen until its compiled copy is gone
     */
    private function forgetCompiledTemplates(string $path): void
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'twig') {
            (new \App\Application\ThemeFiles($this->container))->clearTemplateCache();
        }
    }

    private function getFiles(string $theme): array
    {
        $tree = [
            ['name' => 'resource', 'title' => 'Resource', 'type' => 'dir', 'list' => $this->getFilesRecursion(RESOURCE_DIR)],
        ];

        if (($path = realpath(THEME_DIR . '/' . $theme)) !== false) {
            array_unshift($tree, ['name' => 'theme', 'title' => 'Theme', 'type' => 'dir', 'list' => $this->getFilesRecursion($path)]);
        }

        return $tree;
    }

    private function getFilesRecursion(string $path): array
    {
        $dirs = [];
        $files = [];

        foreach (new \DirectoryIterator($path) as $file) {
            if ($file->isDot() || str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            if ($file->isDir()) {
                $dirs[] = ['name' => $file->getFilename(), 'type' => 'dir', 'list' => $this->getFilesRecursion($path . '/' . $file->getFilename())];
            } else {
                $files[] = ['name' => $file->getFilename(), 'type' => 'file', 'size' => $file->getSize()];
            }
        }

        $by_name = fn ($a, $b) => strnatcasecmp($a['name'], $b['name']);
        usort($dirs, $by_name);
        usort($files, $by_name);

        return array_merge($dirs, $files);
    }
}
