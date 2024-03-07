<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Models\File;
use App\Domain\Models\FileRelated;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property Collection $files
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
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function getDocuments(): Collection
    {
        return $this->files()->where(fn ($query) => $query->where('type', 'LIKE', 'application/%')->orWhere('type', 'LIKE', 'text/%'))->getResults();
    }

    public function getImages(): Collection
    {
        return $this->files()->where(fn ($query) => $query->where('type', 'LIKE', 'image/%'))->getResults();
    }

    public function getAudios(): Collection
    {
        return $this->files()->where(fn ($query) => $query->where('type', 'LIKE', 'audio/%'))->getResults();
    }

    public function getVideos(): Collection
    {
        return $this->files()->where(fn ($query) => $query->where('type', 'LIKE', 'video/%'))->getResults();
    }
}
