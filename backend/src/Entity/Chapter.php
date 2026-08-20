<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChapterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChapterRepository::class)]
#[ORM\Index(fields: ['project', 'spineOrder'], name: 'idx_chapter_project_order')]
class Chapter
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column]
    private int $spineOrder;

    #[ORM\Column(length: 512)]
    private string $href;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title;

    public function __construct(Project $project, int $spineOrder, string $href, ?string $title = null)
    {
        $this->id = Uuid::v7();
        $this->project = $project;
        $this->spineOrder = $spineOrder;
        $this->href = $href;
        $this->title = $title;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getSpineOrder(): int
    {
        return $this->spineOrder;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }
}
