<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\ChapterRepository;
use App\State\ChapterCollectionProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChapterRepository::class)]
#[ORM\Index(fields: ['project', 'spineOrder'], name: 'idx_chapter_project_order')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/projects/{projectId}/chapters',
            uriVariables: [
                // Bez tego API Platform myli 1-elementowa mape uriVariables
                // wygenerowana z wlasnego "id" Chapter z parametrem trasy
                // "projectId" - oba maja po jednym elemencie, wiec automatyczna
                // naprawa niezgodnosci nazw sie nie uruchamia.
                'projectId' => new Link(fromClass: Project::class, toProperty: 'project'),
            ],
            provider: ChapterCollectionProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['chapter:read']],
)]
class Chapter
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['chapter:read', 'segment:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column]
    #[Groups(['chapter:read', 'segment:read'])]
    private int $spineOrder;

    #[ORM\Column(length: 512)]
    private string $href;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['chapter:read', 'segment:read'])]
    private ?string $title;

    /**
     * Liczniki nie maja kolumn - wypelnia je provider przy odczycie, wiec nie
     * moga rozjechac sie z tabela segmentow.
     *
     * @var array<string, int>
     */
    #[Groups(['chapter:read'])]
    private array $segmentCounts = [];

    #[Groups(['chapter:read'])]
    private int $totalSegments = 0;

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

    /**
     * @return array<string, int>
     */
    public function getSegmentCounts(): array
    {
        return $this->segmentCounts;
    }

    /**
     * @param array<string, int> $segmentCounts
     */
    public function setSegmentCounts(array $segmentCounts): void
    {
        $this->segmentCounts = $segmentCounts;
        $this->totalSegments = array_sum($segmentCounts);
    }

    public function getTotalSegments(): int
    {
        return $this->totalSegments;
    }
}
