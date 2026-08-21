<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\SegmentRepository;
use App\State\SegmentCollectionProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SegmentRepository::class)]
#[ORM\Index(fields: ['project', 'status'], name: 'idx_segment_project_status')]
#[ORM\UniqueConstraint(name: 'uniq_segment_node', columns: ['chapter_id', 'node_index', 'sub_index'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/chapters/{chapterId}/segments',
            uriVariables: [
                'chapterId' => new Link(fromClass: Chapter::class, toProperty: 'chapter'),
            ],
            order: ['position' => 'ASC'],
            paginationItemsPerPage: 100,
            provider: SegmentCollectionProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['segment:read']],
)]
class Segment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['segment:read'])]
    private Uuid $id;

    /**
     * Denormalizacja: postep liczymy jednym zapytaniem po projekcie,
     * bez laczenia przez rozdzialy.
     */
    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: Chapter::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Chapter $chapter;

    #[ORM\Column]
    #[Groups(['segment:read'])]
    private int $position;

    #[ORM\Column]
    #[Groups(['segment:read'])]
    private int $nodeIndex;

    #[ORM\Column]
    #[Groups(['segment:read'])]
    private int $subIndex;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['segment:read'])]
    private string $sourceText;

    /** @var array<array-key, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $placeholders;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['segment:read'])]
    private ?string $translatedText = null;

    #[ORM\Column(enumType: SegmentStatus::class)]
    #[Groups(['segment:read'])]
    private SegmentStatus $status = SegmentStatus::Pending;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['segment:read'])]
    private ?string $errorMessage = null;

    /**
     * @param array<array-key, string> $placeholders
     */
    public function __construct(
        Chapter $chapter,
        int $position,
        int $nodeIndex,
        int $subIndex,
        string $sourceText,
        array $placeholders,
    ) {
        $this->id = Uuid::v7();
        $this->chapter = $chapter;
        $this->project = $chapter->getProject();
        $this->position = $position;
        $this->nodeIndex = $nodeIndex;
        $this->subIndex = $subIndex;
        $this->sourceText = $sourceText;
        $this->placeholders = $placeholders;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getChapter(): Chapter
    {
        return $this->chapter;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getNodeIndex(): int
    {
        return $this->nodeIndex;
    }

    public function getSubIndex(): int
    {
        return $this->subIndex;
    }

    public function getSourceText(): string
    {
        return $this->sourceText;
    }

    /**
     * @return array<array-key, string>
     */
    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function getTranslatedText(): ?string
    {
        return $this->translatedText;
    }

    public function setTranslatedText(?string $translatedText): void
    {
        $this->translatedText = $translatedText;
    }

    public function getStatus(): SegmentStatus
    {
        return $this->status;
    }

    public function setStatus(SegmentStatus $status): void
    {
        $this->status = $status;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        ++$this->attempts;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }
}
