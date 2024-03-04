<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Models\File;
use App\Domain\Models\FileRelated;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

/**
 * @property File[] $files
 */
trait FileTrait
{
    public function files()
    {
        return $this->hasManyThrough(
            File::class,
            FileRelated::class,
            'entity_uuid',
            'uuid',
            'uuid',
            'file_uuid',
        );
    }

    public function hasFiles(): int
    {
        return $this->files()->count();
    }

    /** @deprecated */
    public function getFiles(): HasManyThrough
    {
        return $this->files();
    }

    public function getDocuments(): HasManyThrough
    {
        return $this->files()->where(fn ($query) => $query->where('type', 'LIKE', 'application/%')->orWhere('type', 'LIKE', 'text/%'));
    }

    public function getImages(): HasManyThrough
    {
        return $this->files()->where(fn ($query) => $query->where('type', 'LIKE', 'image/%'));
    }

    public function getAudios(): HasManyThrough
    {
        return $this->files()->where(fn ($query) => $query->where('type', 'LIKE', 'audio/%'));
    }

    public function getVideos(): HasManyThrough
    {
        return $this->files()->where(fn ($query) => $query->where('type', 'LIKE', 'video/%'));
    }
}
