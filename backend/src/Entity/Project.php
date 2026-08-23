<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Repository\ProjectRepository;
use App\State\CancelProjectProcessor;
use App\State\CreateProjectProcessor;
use App\State\DeleteProjectProcessor;
use App\State\PauseProjectProcessor;
use App\State\ProjectCollectionProvider;
use App\State\ResumeProjectProcessor;
use App\State\RetryFailedSegmentsProcessor;
use App\State\StartProjectProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Index(fields: ['owner'], name: 'idx_project_owner')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/projects',
            openapi: new OpenApiOperation(
                summary: 'Lists the projects of the authenticated user.',
                description: 'This is the only place the progress counters are filled in: segmentCounts holds the number of segments per status and totalSegments their sum. The single-project operation leaves both empty.',
            ),
            provider: ProjectCollectionProvider::class,
        ),
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
            openapi: new OpenApiOperation(
                summary: 'Uploads a book and creates a project for it.',
                description: 'Takes a multipart form, not JSON: file, title, targetLanguage and ollamaModel are required, sourceLanguage and customPrompt are optional. Parsing runs in the background, so the project comes back as parsing and turns ready once its chapters and segments exist.',
            ),
            inputFormats: ['multipart' => ['multipart/form-data']],
            deserialize: false,
            processor: CreateProjectProcessor::class,
        ),
        // read: true wczytuje encje, bez ktorej "object" w wyrazeniu security
        // byloby puste, a procesor nie dostalby projektu. Te operacje nie maja
        // ciala, stad deserialize: false - a input: false zdejmuje z nich
        // zmyslone "request body" w dokumencie OpenAPI, bo bez tego API
        // Platform obiecuje czytajacemu schemat zapisu zasobu, ktorego te
        // operacje i tak nie czytaja.
        //
        // ReadProvider celowo nie rzuca 404, gdy provider nic nie znajdzie dla
        // metody POST - POST zwykle tworzy zasob. OwnerExtension odfiltrowuje
        // cudzy projekt, wiec "object" bywa tu null i bez pierwszego czlonu
        // wyrazenia voter odmawialby dostepu kodem 403. O braku zasobu
        // decyduje procesor, ktory odpowiada 404 tak samo jak GET i PATCH.
        new Post(
            uriTemplate: '/projects/{id}/start',
            openapi: new OpenApiOperation(
                summary: 'Starts translating the project.',
                description: 'Queues the first pending segment; the chain re-queues itself until the book is done, paused or cancelled. Answers 409 unless the project is ready or cancelled.',
            ),
            security: 'object === null or is_granted("PROJECT_EDIT", object)',
            read: true,
            deserialize: false,
            input: false,
            processor: StartProjectProcessor::class,
        ),
        new Post(
            uriTemplate: '/projects/{id}/pause',
            openapi: new OpenApiOperation(
                summary: 'Pauses a running translation.',
                description: 'The segment in flight finishes and the chain stops there. Answers 409 unless the project is translating.',
            ),
            security: 'object === null or is_granted("PROJECT_EDIT", object)',
            read: true,
            deserialize: false,
            input: false,
            processor: PauseProjectProcessor::class,
        ),
        new Post(
            uriTemplate: '/projects/{id}/resume',
            openapi: new OpenApiOperation(
                summary: 'Resumes a paused translation.',
                description: 'Releases segments left in processing by a worker that died, then restarts the chain from the first pending one. Answers 409 unless the project is paused.',
            ),
            security: 'object === null or is_granted("PROJECT_EDIT", object)',
            read: true,
            deserialize: false,
            input: false,
            processor: ResumeProjectProcessor::class,
        ),
        new Post(
            uriTemplate: '/projects/{id}/cancel',
            openapi: new OpenApiOperation(
                summary: 'Cancels a running or paused translation.',
                description: 'Stops the chain and releases segments left in processing. Translations already made are kept, and the project can be started again. Answers 409 unless the project is translating or paused.',
            ),
            security: 'object === null or is_granted("PROJECT_EDIT", object)',
            read: true,
            deserialize: false,
            input: false,
            processor: CancelProjectProcessor::class,
        ),
        new Post(
            uriTemplate: '/projects/{id}/retry-failed',
            openapi: new OpenApiOperation(
                summary: 'Queues every failed segment for another attempt.',
                description: 'Clears the attempt budget of failed segments, releases segments left in processing and restarts the chain. Answers 409 while the translation is still running, or when nothing failed.',
            ),
            security: 'object === null or is_granted("PROJECT_EDIT", object)',
            read: true,
            deserialize: false,
            input: false,
            processor: RetryFailedSegmentsProcessor::class,
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
