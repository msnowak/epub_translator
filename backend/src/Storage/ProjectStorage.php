<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Project;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class ProjectStorage
{
    public function __construct(
        private Filesystem $filesystem,
        #[Autowire('%app.storage_dir%')]
        private string $storageDir,
    ) {
    }

    public function store(\SplFileInfo $file, Project $project): string
    {
        $directory = $this->directory($project);
        $this->filesystem->mkdir($directory);

        $target = $directory.'/original.epub';
        $this->filesystem->copy($file->getPathname(), $target, true);

        return $target;
    }

    public function path(Project $project): string
    {
        $path = $project->getStoragePath();

        if (null === $path) {
            throw new \LogicException('The project has no stored file yet.');
        }

        return $path;
    }

    public function delete(Project $project): void
    {
        $this->filesystem->remove($this->directory($project));
    }

    private function directory(Project $project): string
    {
        // Katalog per uzytkownik i projekt: kasowanie projektu to usuniecie
        // jednego drzewa, bez ryzyka trafienia w cudze pliki.
        return \sprintf(
            '%s/%s/%s',
            rtrim($this->storageDir, '/'),
            $project->getOwner()->getId(),
            $project->getId(),
        );
    }
}
