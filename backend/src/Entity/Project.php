<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ProjectRepository;
use App\State\CreateProjectProcessor;
use App\State\DeleteProjectProcessor;
use App\State\ProjectCollectionProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Index(fields: ['owner'], name: 'idx_project_owner')]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/projects', provider: ProjectCollectionProvider::class),
        new Get(uriTemplate: '/projects/{id}'),
        new Patch(
            uriTemplate: '/projects/{id}',
            security: 'is_granted("PROJECT_EDIT", object)',
        ),
        new Delete(
            uriTemplate: '/projects/{id}',
            security: 'is_granted("PROJECT_DELETE", object)',
            processor: DeleteProjectProcessor::class,
        ),
        new Post(
            uriTemplate: '/projects',
            status: 201,
            inputFormats: ['multipart' => ['multipart/form-data']],
            deserialize: false,
            processor: CreateProjectProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['project:read']],
    denormalizationContext: ['groups' => ['project:write']],
)]
class Project
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['project:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Podaj tytuł projektu.')]
    #[Groups(['project:read', 'project:write'])]
    private string $title;

    #[ORM\Column(length: 16, nullable: true)]
    #[Groups(['project:read', 'project:write'])]
    private ?string $sourceLanguage = null;

    #[ORM\Column(length: 16)]
    #[Assert\NotBlank(message: 'Wybierz język docelowy.')]
    #[Groups(['project:read', 'project:write'])]
    private string $targetLanguage;

    #[ORM\Column(length: 128)]
    #[Assert\NotBlank(message: 'Wybierz model.')]
    #[Groups(['project:read', 'project:write'])]
    private string $ollamaModel;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['project:read', 'project:write'])]
    private ?string $customPrompt = null;

    #[ORM\Column(enumType: ProjectStatus::class)]
    #[Groups(['project:read'])]
    private ProjectStatus $status = ProjectStatus::Parsing;

    #[ORM\Column(length: 255)]
    #[Groups(['project:read'])]
    private string $originalFilename;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $storagePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['project:read'])]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['project:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['project:read'])]
    private \DateTimeImmutable $updatedAt;

    /**
     * Liczniki postepu nie maja kolumn - wypelnia je provider przy odczycie,
     * wiec nie moga rozjechac sie z tabela segmentow.
     *
     * @var array<string, int>
     */
    #[Groups(['project:read'])]
    private array $segmentCounts = [];

    #[Groups(['project:read'])]
    private int $totalSegments = 0;

    public function __construct(
        User $owner,
        string $title,
        string $targetLanguage,
        string $ollamaModel,
        string $originalFilename,
    ) {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->title = $title;
        $this->targetLanguage = $targetLanguage;
        $this->ollamaModel = $ollamaModel;
        $this->originalFilename = $originalFilename;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getSourceLanguage(): ?string
    {
        return $this->sourceLanguage;
    }

    public function setSourceLanguage(?string $sourceLanguage): void
    {
        $this->sourceLanguage = $sourceLanguage;
    }

    public function getTargetLanguage(): string
    {
        return $this->targetLanguage;
    }

    public function setTargetLanguage(string $targetLanguage): void
    {
        $this->targetLanguage = $targetLanguage;
    }

    public function getOllamaModel(): string
    {
        return $this->ollamaModel;
    }

    public function setOllamaModel(string $ollamaModel): void
    {
        $this->ollamaModel = $ollamaModel;
    }

    public function getCustomPrompt(): ?string
    {
        return $this->customPrompt;
    }

    public function setCustomPrompt(?string $customPrompt): void
    {
        $this->customPrompt = $customPrompt;
    }

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    public function setStatus(ProjectStatus $status): void
    {
        $this->status = $status;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): void
    {
        $this->originalFilename = $originalFilename;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(?string $storagePath): void
    {
        $this->storagePath = $storagePath;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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
