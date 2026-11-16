<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\Models\ApiKey;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\File;
use App\Domain\Models\FormData;
use App\Domain\Models\Page;
use App\Domain\Models\Publication;
use App\Domain\Models\PublicationCategory;
use App\Domain\Models\Review;
use App\Domain\Models\User;
use App\Domain\Models\UserGroup;
use App\Domain\Service\File\FileService;
use App\Domain\Tasks\ConvertImageTask;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Psr\Container\ContainerInterface;

/**
 * Files of the records (products, categories, publications, pages...): what is attached, putting a new file to a record
 * (by an address on the internet or by the content itself), the order and the captions, taking a file away
 *
 * A file is checked before it gets to the site: the kind (by the name AND by what the file really is), the size, the number of files of a record.
 * A file that is taken away from a record stays in the file store: it can be used by another record
 */
class EntityFiles
{
    /**
     * Records that have files: the key of a tool => the model, the API entity a key needs rights for (null - full access keys only)
     * and the part of the name of the event of a change
     */
    public const ENTITIES = [
        'catalog_product' => ['model' => CatalogProduct::class, 'scope' => 'catalog/product', 'event' => 'catalog:product'],
        'catalog_category' => ['model' => CatalogCategory::class, 'scope' => 'catalog/category', 'event' => 'catalog:category'],
        'publication' => ['model' => Publication::class, 'scope' => 'publication', 'event' => 'publication'],
        'publication_category' => ['model' => PublicationCategory::class, 'scope' => 'publication/category', 'event' => 'publication:category'],
        'page' => ['model' => Page::class, 'scope' => 'page', 'event' => 'page'],
        'user' => ['model' => User::class, 'scope' => 'user', 'event' => 'user'],
        'user_group' => ['model' => UserGroup::class, 'scope' => 'user/group', 'event' => 'user:group'],
        'review' => ['model' => Review::class, 'scope' => 'review', 'event' => 'review'],
        'form_data' => ['model' => FormData::class, 'scope' => null, 'event' => null],
    ];

    /**
     * What may be put on the site: the extension => what the file has to be. Nothing that a browser runs or a server executes
     * (html, svg, js, php, archives, programs): only what is listed is allowed, and the content has to be that kind of file
     */
    private const IMAGES = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'], 'avif' => ['image/avif']];

    private const OFFICE = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'];

    private const OTHER = ['pdf', 'txt', 'csv', 'rtf', 'mp3', 'mp4', 'webm'];

    public const MAX_URL_BYTES = 20 * 1024 * 1024;

    public const MAX_CONTENT_BYTES = 5 * 1024 * 1024;

    public const MAX_FILES = 100;

    public const MAX_COMMENT = 500;

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Whether a key may read / write the files of this kind of records
     */
    public static function allows(ApiKey $apiKey, string $entity, string $mode): bool
    {
        $scope = self::ENTITIES[$entity]['scope'] ?? null;

        return $scope === null ? ($apiKey->is_full_access && $apiKey->can('file', $mode)) : $apiKey->can($scope, $mode);
    }

    /**
     * Whether a key may do something with the files of at least one kind of records (to show the tool to it)
     */
    public static function allowsAny(?ApiKey $apiKey, string $mode): bool
    {
        if ($apiKey === null) {
            return false;
        }

        foreach (array_keys(self::ENTITIES) as $entity) {
            if (self::allows($apiKey, $entity, $mode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \DomainException
     */
    public function find(?ApiKey $apiKey, string $entity, mixed $uuid, string $mode): Model
    {
        if (!isset(self::ENTITIES[$entity])) {
            throw new \InvalidArgumentException('Field entity must be one of: ' . implode(', ', array_keys(self::ENTITIES)));
        }
        if ($apiKey === null || !self::allows($apiKey, $entity, $mode)) {
            throw new \DomainException("The API key has no {$mode} access to {$entity}");
        }
        if (!is_string($uuid) || !\Ramsey\Uuid\Uuid::isValid($uuid)) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var null|Model $record */
        $record = self::ENTITIES[$entity]['model']::query()->where('uuid', $uuid)->first();

        if ($record === null) {
            throw new \InvalidArgumentException("No {$entity} with this uuid");
        }
        // a staff account is not edited through tools
        if ($mode === 'write' && $record instanceof User && (array) $record->getRelationValue('group')?->access !== []) {
            throw new \DomainException('This is a staff account (admin panel access), it can not be changed here');
        }

        return $record;
    }

    /**
     * @return array<int, array{uuid: string, name: string, type: string, size: int, order: int, comment: string, url: string, thumbnail: null|string}>
     */
    public function list(Model $record): array
    {
        $homepage = rtrim((string) $this->parameter('common_homepage'), '/');

        $cards = [];

        /** @var File $file */
        foreach ($this->files($record)->get() as $file) {
            $cards[] = $this->card($file, $homepage, (int) $file->getRelation('pivot')->order, (string) $file->getRelation('pivot')->comment);
        }

        return $cards;
    }

    /**
     * Put a file on the record: from an address on the internet or from the content itself
     *
     * @param array{url?: string, content_base64?: string, name?: string} $source
     *
     * @throws \InvalidArgumentException
     * @throws \DomainException
     *
     * @return array<string, mixed>
     */
    public function attach(Model $record, array $source, ?string $comment, ?int $order, bool $dryRun): array
    {
        [$tmp, $name] = $this->acquire($source);

        try {
            $info = $this->inspect($tmp, $name);

            if ($this->files($record)->count() >= self::MAX_FILES && !$this->isAttached($record, $info['hash'])) {
                throw new \DomainException('A record has up to ' . self::MAX_FILES . ' files');
            }

            /** @var null|File $existing */
            $existing = File::query()->where('hash', $info['hash'])->first();
            $attached = $existing !== null && $this->isAttached($record, $existing->hash);

            if ($dryRun) {
                return [
                    'dry_run' => true,
                    'name' => $info['name'],
                    'type' => $info['type'],
                    'size' => $info['size'],
                    'file_exists_on_site' => $existing !== null,
                    'already_attached' => $attached,
                    'would_be_attached' => !$attached,
                ];
            }

            $created = $existing === null;
            $file = $existing ?? $this->container->get(FileService::class)->createFromPath($tmp, $info['name']);

            if ($file === null) {
                throw new \DomainException('The file could not be saved');
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        $comment = $comment === null ? null : mb_substr(trim($comment), 0, self::MAX_COMMENT);

        if ($attached) {
            $update = array_filter(['comment' => $comment, 'order' => $order], fn ($value) => $value !== null);

            if ($update) {
                $this->files($record)->updateExistingPivot($file->uuid, $update);
            }
        } else {
            $this->files($record)->attach($file->uuid, ['comment' => $comment ?? '', 'order' => $order ?? $this->nextOrder($record)]);
        }

        // sizes of pictures are made in the background, the same as for the files that are uploaded in the admin panel
        if ($created && str_starts_with((string) $file->type, 'image/')) {
            $task = new ConvertImageTask($this->container);
            $task->execute(['uuid' => [(string) $file->uuid]]);
            \App\Domain\AbstractTask::worker($task);
        }

        $this->changed($record);

        $files = $this->list($record->refresh());

        return [
            'dry_run' => false,
            'created_file' => $created,
            'already_attached' => $attached,
            'file' => $this->pick($files, (string) $file->uuid),
            'files_of_record' => count($files),
        ];
    }

    /**
     * Take files away from a record (not from the store)
     *
     * @return array{detached: array<int, string>, used_elsewhere: array<int, string>, files_of_record: int}
     */
    public function detach(Model $record, string $fileUuid): array
    {
        if (!\Ramsey\Uuid\Uuid::isValid($fileUuid) || !$this->hasFile($record, $fileUuid)) {
            throw new \InvalidArgumentException('This file is not attached to the record, see entity_file_list');
        }

        $this->files($record)->detach($fileUuid);

        $others = $this->db()->table('file_related')->where('file_uuid', $fileUuid)->count();

        $this->changed($record);

        return [
            'detached' => [$fileUuid],
            // the file stays in the store; if nobody uses it the administrator deletes it in the file manager
            'used_elsewhere' => $others > 0 ? [$fileUuid] : [],
            'files_of_record' => $this->files($record)->count(),
        ];
    }

    /**
     * New order and captions of the files of a record
     *
     * @param null|array<int, string>    $order    uuids of the files in the wanted order (the ones that are not listed go after them)
     * @param null|array<string, mixed>  $comments file uuid => caption
     *
     * @return array<int, array<string, mixed>>
     */
    public function arrange(Model $record, ?array $order, ?array $comments): array
    {
        $current = $this->files($record)->get()->pluck('uuid')->map(fn ($uuid) => (string) $uuid)->all();

        if ($order !== null) {
            if (!array_is_list($order) || array_diff($order, $current) !== [] || count($order) !== count(array_unique($order))) {
                throw new \InvalidArgumentException('order must be a list of uuids of the files of this record, each once (see entity_file_list)');
            }

            foreach (array_values(array_merge($order, array_diff($current, $order))) as $position => $uuid) {
                $this->files($record)->updateExistingPivot($uuid, ['order' => $position + 1]);
            }
        }
        if ($comments !== null) {
            foreach ($comments as $uuid => $text) {
                if (!in_array((string) $uuid, $current, true) || !is_scalar($text)) {
                    throw new \InvalidArgumentException("comments: the file {$uuid} is not attached to the record");
                }

                $this->files($record)->updateExistingPivot((string) $uuid, ['comment' => mb_substr(trim((string) $text), 0, self::MAX_COMMENT)]);
            }
        }

        $this->changed($record);

        return $this->list($record->refresh());
    }

    /**
     * The file in a temporary place and the name it will have
     *
     * @param array{url?: string, content_base64?: string, name?: string} $source
     *
     * @throws \InvalidArgumentException
     * @throws \DomainException
     *
     * @return array{0: string, 1: string}
     */
    private function acquire(array $source): array
    {
        $url = trim((string) ($source['url'] ?? ''));
        $content = (string) ($source['content_base64'] ?? '');

        if (($url === '') === ($content === '')) {
            throw new \InvalidArgumentException('Pass the file either as url or as content_base64 (with name)');
        }

        $name = trim((string) ($source['name'] ?? ''));

        if ($url !== '') {
            if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
                throw new \InvalidArgumentException('url must start with https:// or http://');
            }

            $name = $name !== '' ? $name : rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
            $this->extension($name, 'The kind of the file is taken from the name: the address has none, pass "name" with the extension, ex: photo.jpg');

            // the kind is known from the name: what is not allowed is not downloaded at all
            $tmp = FileService::getFileFromRemote($url, 0, self::MAX_URL_BYTES);

            if ($tmp === false) {
                throw new \DomainException('The file could not be downloaded: the address is not available, is not public, or the file is bigger than ' . (self::MAX_URL_BYTES / 1024 / 1024) . ' MB');
            }

            return [$tmp, $name];
        }

        if ($name === '') {
            throw new \InvalidArgumentException('Field name (with the extension, ex: photo.jpg) is required with content_base64');
        }

        $ext = $this->extension($name, 'The name needs an extension that tells the kind of the file, ex: photo.jpg');

        // the size of the text is checked first: it is not decoded if it is too long
        if (strlen($content) > (int) ceil(self::MAX_CONTENT_BYTES * 4 / 3) + 8) {
            throw new \InvalidArgumentException('The content is bigger than ' . (self::MAX_CONTENT_BYTES / 1024 / 1024) . ' MB, give the file as url');
        }

        $bytes = base64_decode($content, true);

        if ($bytes === false || $bytes === '') {
            throw new \InvalidArgumentException('content_base64 is not valid base64');
        }
        if (strlen($bytes) > self::MAX_CONTENT_BYTES) {
            throw new \InvalidArgumentException('The content is bigger than ' . (self::MAX_CONTENT_BYTES / 1024 / 1024) . ' MB, give the file as url');
        }

        $tmp = $this->tempDir() . '/mcp-' . uniqid() . '.' . $ext;

        if (file_put_contents($tmp, $bytes) === false) {
            throw new \DomainException('The file could not be written');
        }

        return [$tmp, $name];
    }

    /**
     * Name, kind and size of the file, after the check that it is what its name says and may be put on the site
     *
     * @throws \InvalidArgumentException
     *
     * @return array{name: string, type: string, size: int, hash: string}
     */
    private function inspect(string $path, string $name): array
    {
        $ext = $this->extension($name, 'The name needs an extension that tells the kind of the file');
        $size = (int) filesize($path);

        if ($size === 0) {
            throw new \InvalidArgumentException('The file is empty');
        }

        $mime = (string) @mime_content_type($path);

        if (isset(self::IMAGES[$ext])) {
            // a picture is what a picture reader makes of it, not what a header says
            $image = @getimagesize($path);

            if ($image === false || !in_array($image['mime'], self::IMAGES[$ext], true)) {
                throw new \InvalidArgumentException("The file is not a real .{$ext} picture");
            }
        } else {
            $this->checkContent($ext, $mime);
        }

        return ['name' => File::prepareName($name), 'type' => $mime, 'size' => $size, 'hash' => (string) File::info($path)['hash']];
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function checkContent(string $ext, string $mime): void
    {
        $ok = match (true) {
            $ext === 'pdf' => $mime === 'application/pdf',
            in_array($ext, ['txt', 'csv'], true) => in_array($mime, ['text/plain', 'text/csv', 'application/csv'], true),
            $ext === 'rtf' => in_array($mime, ['text/rtf', 'application/rtf'], true),
            in_array($ext, self::OFFICE, true) => preg_match('#^application/(msword|vnd\.|x-ole-storage|CDFV2|zip|octet-stream)#i', $mime) === 1,
            $ext === 'mp3' => str_starts_with($mime, 'audio/'),
            default => str_starts_with($mime, 'video/') || $mime === 'application/ogg',
        };

        if (!$ok) {
            throw new \InvalidArgumentException("The file is not a real .{$ext} file (it is {$mime})");
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function extension(string $name, string $hint): string
    {
        $ext = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($ext === '') {
            throw new \InvalidArgumentException($hint);
        }
        if (!isset(self::IMAGES[$ext]) && !in_array($ext, [...self::OFFICE, ...self::OTHER], true)) {
            throw new \InvalidArgumentException("Files .{$ext} are not allowed. Allowed: " . implode(', ', array_merge(array_keys(self::IMAGES), self::OFFICE, self::OTHER)));
        }
        if (mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('The name of the file is up to 120 characters');
        }

        return $ext;
    }

    /**
     * The files of a record: the relation of the trait HasFiles
     */
    private function files(Model $record): MorphToMany
    {
        /** @var MorphToMany $relation */
        $relation = call_user_func([$record, 'files']);

        return $relation;
    }

    private function isAttached(Model $record, string $hash): bool
    {
        return $this->files($record)->where('file.hash', $hash)->exists();
    }

    private function hasFile(Model $record, string $fileUuid): bool
    {
        return $this->files($record)->where('file.uuid', $fileUuid)->exists();
    }

    private function nextOrder(Model $record): int
    {
        return (int) $this->db()->table('file_related')
            ->where('entity_uuid', (string) $record->getKey())
            ->where('object_type', $record->getMorphClass())
            ->max('order') + 1;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     *
     * @return null|array<string, mixed>
     */
    private function pick(array $files, string $uuid): ?array
    {
        foreach ($files as $file) {
            if ($file['uuid'] === $uuid) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array{uuid: string, name: string, type: string, size: int, order: int, comment: string, url: string, thumbnail: null|string}
     */
    private function card(File $file, string $homepage, int $order, string $comment): array
    {
        $image = str_starts_with((string) $file->type, 'image/');

        return [
            'uuid' => (string) $file->uuid,
            'name' => $file->filename(),
            // the store keeps what the detector says ("image/png; charset=binary"), the caller needs the type
            'type' => trim(explode(';', (string) $file->type)[0]),
            'size' => (int) $file->size,
            'order' => $order,
            'comment' => $comment,
            'url' => $homepage . $file->public_path(),
            'thumbnail' => $image && is_file($file->internal_path('small')) ? $homepage . $file->public_path('small') : null,
        ];
    }

    /**
     * Plugins and the site react to a changed record: the same event as REST gives
     */
    private function changed(Model $record): void
    {
        foreach (self::ENTITIES as $config) {
            if ($record instanceof $config['model'] && $config['event'] !== null) {
                $this->container->get(PubSub::class)->publish("api:{$config['event']}:edit", $record);

                return;
            }
        }
    }

    private function parameter(string $name): mixed
    {
        return $this->container->get(\App\Domain\Service\Parameter\ParameterService::class)->read(['name' => $name])->value ?? '';
    }

    private function tempDir(): string
    {
        /** @var false|string $dir */
        $dir = constant('CACHE_DIR');

        return is_string($dir) && $dir !== '' ? $dir : sys_get_temp_dir();
    }

    private function db(): Connection
    {
        return $this->container->get(Connection::class);
    }
}
