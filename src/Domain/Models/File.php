<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Application\i18n;
use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Meta;
use App\Domain\Enums\PageType;
use App\Domain\Traits\FileTrait;
use DateTime;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $uuid
 * @property string $name
 * @property string $ext
 * @property string $type
 * @property int $size
 * @property string $salt
 * @property string $hash
 * @property bool $private
 * @property DateTime $date
 */
class File extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'file';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = 'date';

    protected $fillable = [
        'name',
        'ext',
        'type',
        'size',
        'salt',
        'hash',
        'private',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'name' => 'string',
        'ext' => 'string',
        'type' => 'string',
        'size' => 'int',
        'salt' => 'string',
        'hash' => 'string',
        'private' => Boolean::class,
        'date' => 'datetime',
    ];

    protected $attributes = [
        'private' => false,
    ];

    public function relations(): HasMany
    {
        return $this->hasMany(
            File::class,
            'file_uuid',
            'uuid'
        );
    }

    public static function prepareName($name)
    {
        $replacements = [
            '!', '*', "'",
            '(', ')', ';',
            ':', '@', '&',
            '=', '+', '$',
            ',', '/', '?',
            '%', '#', '[',
            ']',
        ];

        $name = urldecode($name);
        $name = str_replace(' ', '_', $name);
        $name = str_replace($replacements, '', $name);
        $name = i18n::getTranslatedText($name);

        return mb_strtolower($name);
    }

    public static function info($path): array
    {
        $info = pathinfo($path);

        return [
            'dir' => $info['dirname'],
            'name' => isset($info['filename']) ? static::prepareName($info['filename']) : '',
            'ext' => isset($info['extension']) ? mb_strtolower($info['extension']) : '',
            'type' => addslashes(@exec('file -bi "' . $path . '"')),
            'size' => filesize($path),
            'hash' => sha1_file($path),
        ];
    }

    public function filename(): string
    {
        return $this->name . ($this->ext ? '.' . $this->ext : '');
    }

    public function size(): string
    {
        return str_convert_size($this->size);
    }

    // validate size is correct and check exist file
    protected function isValidSizeAndFileExists(string $size): bool
    {
        if (in_array($size, ['middle', 'small'], true)) {
            return file_exists(UPLOAD_DIR . '/' . $this->salt . '/' . $size . '/' . $this->filename());
        }

        return false;
    }

    public function dir(string $size = ''): string
    {
        return UPLOAD_DIR . '/' . $this->salt . ($size && $this->isValidSizeAndFileExists($size) ? '/' . $size : '');
    }

    public function internal_path(string $size = ''): string
    {
        return $this->dir($size) . '/' . $this->filename();
    }

    public function public_path(string $size = ''): string
    {
        return '/uploads/' . $this->salt . ($size && $this->isValidSizeAndFileExists($size) ? '/' . $size : '') . '/' . $this->filename();
    }
}
