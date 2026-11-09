<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\Traits\HasParameters;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Psr\Container\ContainerInterface;

/**
 * Files of the active theme (templates) and of the resource folder (css, js) for the tools that read and edit them
 *
 * Paths look like in the admin editor: `theme/page.twig`, `resource/css/style.css`.
 * Everything that leaves the two folders - `..`, hidden names, links pointing out - is refused.
 * Every write keeps the previous version, see backups() / restore()
 */
class ThemeFiles
{
    use HasParameters;

    /**
     * Text files a tool may write; everything else (php, images, fonts, archives...) is read only or not listed
     */
    public const EDITABLE = ['twig', 'css', 'js', 'html', 'txt', 'md'];

    public const MAX_SIZE = 300 * 1024;

    private const MAX_FILES = 2000;

    private const KEEP_BACKUPS = 20;

    protected ContainerInterface $container;

    protected ArrayCache $arrayCache;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->arrayCache = $container->get(ArrayCache::class);
    }

    /**
     * @return array<string, string> section => absolute folder
     */
    public function roots(): array
    {
        $roots = [];

        if (($theme = realpath(THEME_DIR . '/' . $this->parameter('common_theme', 'default'))) !== false) {
            $roots['theme'] = $theme;
        }
        // the constants are realpath() of the folders: false when a folder is missing
        /** @var false|string $resource */
        $resource = constant('RESOURCE_DIR');

        if (is_string($resource) && $resource !== '') {
            $roots['resource'] = $resource;
        }

        return $roots;
    }

    /**
     * @return array<int, array{path: string, size: int, modified: string, editable: bool}>
     */
    public function list(?string $section = null, ?string $search = null, bool $editableOnly = true): array
    {
        $files = [];

        foreach ($this->roots() as $name => $root) {
            if ($section !== null && $section !== $name) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                $relative = ltrim(substr($file->getPathname(), strlen($root)), '/');

                if (!$file->isFile() || $file->isLink() || $this->hasHiddenSegment($relative)) {
                    continue;
                }

                $editable = $this->isEditable($relative);

                if (($editableOnly && !$editable) || ($search !== null && $search !== '' && mb_stripos($relative, $search) === false)) {
                    continue;
                }

                $files[] = [
                    'path' => "{$name}/{$relative}",
                    'size' => (int) $file->getSize(),
                    'modified' => datetime($file->getMTime())->format('Y-m-d H:i'),
                    'editable' => $editable,
                ];

                if (count($files) >= self::MAX_FILES) {
                    break 2;
                }
            }
        }

        usort($files, fn (array $a, array $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \DomainException
     *
     * @return array{path: string, content: string, version: string, size: int, modified: string, editable: bool}
     */
    public function read(string $path): array
    {
        [, $absolute, , $exists] = $this->resolve($path);

        if (!$exists) {
            throw new \DomainException("File {$path} does not exist");
        }
        if (filesize($absolute) > self::MAX_SIZE) {
            throw new \DomainException('The file is bigger than ' . (self::MAX_SIZE / 1024) . ' KB, it is not shown');
        }

        $content = (string) file_get_contents($absolute);

        if (!mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            throw new \DomainException('This is not a text file');
        }

        return [
            'path' => $path,
            'content' => $content,
            'version' => hash('sha256', $content),
            'size' => strlen($content),
            'modified' => datetime(filemtime($absolute))->format('Y-m-d H:i'),
            'editable' => $this->isEditable($absolute),
        ];
    }

    /**
     * Write a new content. The file must be read before: $expectedVersion is the version that was read,
     * a file changed since (by the admin panel, by another call) is not overwritten.
     * A new file (not existing) is created when no version is passed.
     *
     * @throws \InvalidArgumentException when the path or the content is not allowed
     * @throws \DomainException          when the file changed or cannot be written
     *
     * @return array{path: string, created: bool, changed: bool, version: string, added: int, removed: int, backup: null|string, dry_run: bool}
     */
    public function write(string $path, string $content, ?string $expectedVersion, bool $dryRun = false): array
    {
        [, $absolute, , $exists] = $this->resolve($path);

        if (!$this->isEditable($absolute)) {
            throw new \InvalidArgumentException('Only files of these kinds can be written: ' . implode(', ', self::EDITABLE));
        }

        $this->validate($path, $content);

        $old = '';

        if ($exists) {
            $old = (string) file_get_contents($absolute);

            if (blank($expectedVersion)) {
                throw new \InvalidArgumentException('Read the file first and pass its "version" as expected_version: the file exists');
            }
            if (!hash_equals(hash('sha256', $old), (string) $expectedVersion)) {
                throw new \DomainException('The file changed since you read it. Read it again and apply your change to the new text');
            }
        } elseif (!blank($expectedVersion)) {
            throw new \DomainException('The file does not exist (anymore), expected_version is only for existing files');
        }

        [$added, $removed] = $this->diff($old, $content);
        $result = [
            'path' => $path,
            'created' => !$exists,
            'changed' => $old !== $content,
            'version' => hash('sha256', $content),
            'added' => $added,
            'removed' => $removed,
            'backup' => null,
            'dry_run' => $dryRun,
        ];

        if ($old === $content || $dryRun) {
            return $result;
        }

        if ($exists) {
            $result['backup'] = $this->backup($path, $old);
        }

        $this->put($absolute, $content);
        $this->afterWrite($absolute);

        return $result;
    }

    /**
     * Previous versions of the file, newest first
     *
     * @return array<int, array{id: string, saved: string, size: int}>
     */
    public function backups(string $path): array
    {
        $this->resolve($path);

        $files = glob($this->backupDir($path) . '/*.bak') ?: [];
        rsort($files);

        return array_map(fn (string $file) => [
            'id' => basename($file, '.bak'),
            'saved' => datetime(filemtime($file))->format('Y-m-d H:i:s'),
            'size' => (int) filesize($file),
        ], $files);
    }

    /**
     * Put a previous version back. The current content is kept as a version too, so a restore can be undone
     *
     * @throws \DomainException
     *
     * @return array{path: string, restored: string, version: string, backup: null|string, dry_run: bool}
     */
    public function restore(string $path, string $id, bool $dryRun = false): array
    {
        [, $absolute, , $exists] = $this->resolve($path);

        if (preg_match('/^\d{8}-\d{6}-[0-9a-f]{8}$/', $id) !== 1 || !is_file($file = $this->backupDir($path) . "/{$id}.bak")) {
            throw new \DomainException('No such version of the file, see the list of versions');
        }
        if (!$this->isEditable($absolute)) {
            throw new \InvalidArgumentException('Only files of these kinds can be written: ' . implode(', ', self::EDITABLE));
        }

        $content = (string) file_get_contents($file);
        $this->validate($path, $content);

        $backup = null;

        if ($exists) {
            $current = (string) file_get_contents($absolute);

            if ($current === $content) {
                throw new \DomainException('The file already has this version');
            }

            if (!$dryRun) {
                $backup = $this->backup($path, $current);
            }
        }

        if (!$dryRun) {
            $this->put($absolute, $content);
            $this->afterWrite($absolute);
        }

        return ['path' => $path, 'restored' => $id, 'version' => hash('sha256', $content), 'backup' => $backup, 'dry_run' => $dryRun];
    }

    /**
     * Whether editing of the files is turned on in the site settings (off by default)
     */
    public function isWriteEnabled(): bool
    {
        return $this->parameter('mcp_theme_edit', 'no') === 'yes';
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array{0: string, 1: string, 2: string, 3: bool} section, absolute path, path inside of the section, exists
     */
    public function resolve(string $path): array
    {
        $path = trim($path);

        if (preg_match('#^(theme|resource)/(.+)$#', $path, $match) !== 1) {
            throw new \InvalidArgumentException('The path must start with theme/ or resource/, ex: theme/page.twig');
        }

        $roots = $this->roots();

        if (!isset($roots[$match[1]])) {
            throw new \InvalidArgumentException("The folder \"{$match[1]}\" of the site was not found");
        }

        $segments = explode('/', $match[2]);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.') || mb_strlen($segment) > 120 || preg_match('/^[A-Za-z0-9._ -]+$/', $segment) !== 1) {
                throw new \InvalidArgumentException('The path is not allowed: letters, digits, dot, dash and underscore only, no hidden names, no "..", no empty parts');
            }
        }

        $root = $roots[$match[1]];
        $relative = implode('/', $segments);
        $absolute = $root . '/' . $relative;

        if (file_exists($absolute) || is_link($absolute)) {
            $real = realpath($absolute);

            // a link that leads out of the folder is out of the folder
            if ($real === false || !str_starts_with($real, $root . '/') || !is_file($real) || is_link($absolute)) {
                throw new \InvalidArgumentException('The path is not a file of the site');
            }

            return [$match[1], $real, $relative, true];
        }

        $parent = realpath(dirname($absolute));

        if ($parent === false || ($parent !== $root && !str_starts_with($parent, $root . '/'))) {
            throw new \InvalidArgumentException('The folder of the new file does not exist; new folders are not created here');
        }

        return [$match[1], $absolute, $relative, false];
    }

    public function isEditable(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EDITABLE, true);
    }

    /**
     * Compiled templates are cached and not looked at again on a working site: without this a change would wait for a restart
     */
    public function clearTemplateCache(): void
    {
        /** @var false|string $cache */
        $cache = constant('CACHE_DIR');

        if (!is_string($cache) || $cache === '') {
            return;
        }

        // the cache of Twig: folders named by two hex digits
        foreach (glob($cache . '/[0-9a-f][0-9a-f]', GLOB_ONLYDIR) ?: [] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
                $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }

            @rmdir($dir);
        }
    }

    private function hasHiddenSegment(string $relative): bool
    {
        foreach (explode('/', $relative) as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validate(string $path, string $content): void
    {
        // bytes, not characters
        if (strlen($content) > self::MAX_SIZE) {
            throw new \InvalidArgumentException('The text is bigger than ' . (self::MAX_SIZE / 1024) . ' KB');
        }
        if (!mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            throw new \InvalidArgumentException('The text must be UTF-8 text');
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'twig') {
            $this->checkTwig($path, $content);
        }
    }

    /**
     * A template that does not compile breaks the page that uses it - the site shows an error to everyone
     *
     * @throws \InvalidArgumentException
     */
    private function checkTwig(string $path, string $content): void
    {
        /** @var \Twig\Environment $twig */
        $twig = $this->container->get('view')->getEnvironment();

        try {
            $twig->parse($twig->tokenize(new \Twig\Source($content, $path)));
        } catch (\Twig\Error\SyntaxError $e) {
            throw new \InvalidArgumentException('The template has a syntax error, nothing is saved: ' . $e->getRawMessage() . ' (line ' . $e->getTemplateLine() . ')');
        }
    }

    /**
     * Number of lines that are new and lines that are gone (not a patch, enough to see how big a change is)
     *
     * @return array{0: int, 1: int}
     */
    private function diff(string $old, string $new): array
    {
        $before = array_count_values($old === '' ? [] : explode("\n", $old));
        $after = array_count_values($new === '' ? [] : explode("\n", $new));

        $added = 0;
        $removed = 0;

        foreach ($after as $line => $count) {
            $added += max(0, $count - ($before[$line] ?? 0));
        }
        foreach ($before as $line => $count) {
            $removed += max(0, $count - ($after[$line] ?? 0));
        }

        return [$added, $removed];
    }

    private function backupDir(string $path): string
    {
        return VAR_DIR . '/theme-backups/' . str_replace('/', '__', trim($path));
    }

    private function backup(string $path, string $content): string
    {
        $dir = $this->backupDir($path);

        if (!is_dir($dir) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new \DomainException('The backup folder cannot be created, nothing is saved');
        }

        $id = datetime()->format('Ymd-His') . '-' . substr(hash('sha256', $content), 0, 8);

        if (file_put_contents("{$dir}/{$id}.bak", $content) === false) {
            throw new \DomainException('The backup cannot be written, nothing is saved');
        }

        // old versions go: the newest ones stay
        $files = glob("{$dir}/*.bak") ?: [];
        rsort($files);

        foreach (array_slice($files, self::KEEP_BACKUPS) as $old) {
            @unlink($old);
        }

        return $id;
    }

    private function put(string $absolute, string $content): void
    {
        if (@file_put_contents($absolute, $content, LOCK_EX) === false) {
            throw new \DomainException('The file cannot be written (access rights of the folder or of the file)');
        }
    }

    private function afterWrite(string $absolute): void
    {
        if (strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) === 'twig') {
            $this->clearTemplateCache();
        }
    }
}
