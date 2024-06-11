<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Models\File;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property Collection<File> $files
 * @property Collection<File> $documents
 * @property Collection<File> $images
 * @property Collection<File> $audios
 * @property Collection<File> $videos
 */
trait HasFiles
{
    public function files(): MorphToMany
    {
        return $this
            ->morphToMany(
                File::class,
                'object',
                'file_related',
                'entity_uuid',
                'file_uuid',
            )
            ->withPivot('comment', 'order')
            ->orderBy('file_related.order');
    }

    public function documents(): MorphToMany
    {
        return $this
            ->files()
            ->where('type', 'like', 'application/%')
            ->orWhere('type', 'like', 'text/%');
    }

    public function images(): MorphToMany
    {
        return $this
            ->files()
            ->where('type', 'like', 'image/%');
    }

    public function audios(): MorphToMany
    {
        return $this
            ->files()
            ->where('type', 'like', 'audio/%');
    }

    public function videos(): MorphToMany
    {
        return $this
            ->files()
            ->where('type', 'like', 'video/%');
    }
}
