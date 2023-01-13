<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Entities\File;
use App\Domain\Entities\FileRelation;
use Doctrine\Common\Collections\ArrayCollection;
use Illuminate\Support\Collection;

/**
 * @property File[] $files
 */
trait FileTrait
{
    public function addFile(FileRelation $file): void
    {
        $this->files[] = $file;
    }

    public function addFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->addFile($file);
        }
    }

    public function removeFile(FileRelation $file): void
    {
        foreach ($this->files as $key => $value) {
            if ($file === $value) {
                unset($this->files[$key]);
            }
        }
    }

    public function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->removeFile($file);
        }
    }

    public function clearFiles(): void
    {
        foreach ($this->files as $key => $file) {
            unset($this->files[$key]);
        }
    }

    public function getFiles($raw = false): array|ArrayCollection|Collection
    {
        return $raw ? $this->files : collect($this->files);
    }

    public function getAudios(): array|Collection
    {
        return $this->getFiles()->filter(fn ($item) => str_starts_with($item->getType(), 'audio/'));
    }

    public function getDocuments(): array|Collection
    {
        return $this->getFiles()->filter(fn ($item) => str_starts_with($item->getType(), 'application/') || str_starts_with($item->getType(), 'text/'));
    }

    public function getImages(): array|Collection
    {
        return $this->getFiles()->filter(fn ($item) => str_starts_with($item->getType(), 'image/'));
    }

    public function getVideos(): array|Collection
    {
        return $this->getFiles()->filter(fn ($item) => str_starts_with($item->getType(), 'video/'));
    }

    public function hasFiles(): int
    {
        return count($this->files);
    }
}
