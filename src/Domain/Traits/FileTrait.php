<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Models\File;
use App\Domain\Models\FileRelated;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property Collection $files
 */
trait FileTrait
{
    public function files(): MorphToMany
    {
        return $this->morphToMany(
            File::class,
            'object',
            'file_related',
            'entity_uuid',
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
        return $this->files()->withPivot('comment', 'order')->orderBy('file_related.order')->getResults();
    }

    public function getDocuments(): Collection
    {
        return $this->files()->withPivot('comment', 'order')->orderBy('file_related.order')->where(fn ($query) => $query->where('type', 'LIKE', 'application/%')->orWhere('type', 'LIKE', 'text/%'))->getResults();
    }

    public function getImages(): Collection
    {
        return $this->files()->withPivot('comment', 'order')->orderBy('file_related.order')->where(fn ($query) => $query->where('type', 'LIKE', 'image/%'))->getResults();
    }

    public function getAudios(): Collection
    {
        return $this->files()->withPivot('comment', 'order')->orderBy('file_related.order')->where(fn ($query) => $query->where('type', 'LIKE', 'audio/%'))->getResults();
    }

    public function getVideos(): Collection
    {
        return $this->files()->withPivot('comment', 'order')->orderBy('file_related.order')->where(fn ($query) => $query->where('type', 'LIKE', 'video/%'))->getResults();
    }
}
