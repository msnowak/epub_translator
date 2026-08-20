<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Project;
use App\Entity\ProjectStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectFactory
{
    public static function create(
        EntityManagerInterface $entityManager,
        User $owner,
        string $title = 'Testowa książka',
        ProjectStatus $status = ProjectStatus::Ready,
    ): Project {
        $project = new Project($owner, $title, 'pl', 'llama3.1:8b', 'book.epub');
        $project->setStatus($status);

        $entityManager->persist($project);
        $entityManager->flush();

        return $project;
    }
}
