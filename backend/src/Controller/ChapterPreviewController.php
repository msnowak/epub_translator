<?php

declare(strict_types=1);

namespace App\Controller;

use App\Epub\InvalidEpubException;
use App\Http\ProblemResponse;
use App\Preview\ChapterPreviewRenderer;
use App\Repository\ChapterRepository;
use App\Repository\ProjectRepository;
use App\Security\ProjectVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ChapterPreviewController
{
    #[Route(
        '/api/projects/{id}/preview/{chapterId}',
        name: 'api_chapter_preview',
        methods: ['GET'],
    )]
    public function __invoke(
        string $id,
        string $chapterId,
        Security $security,
        ProjectRepository $projects,
        ChapterRepository $chapters,
        ChapterPreviewRenderer $renderer,
    ): Response {
        if (!Uuid::isValid($id) || !Uuid::isValid($chapterId)) {
            return $this->notFound();
        }

        $project = $projects->find(Uuid::fromString($id));

        if (null === $project || !$security->isGranted(ProjectVoter::VIEW, $project)) {
            return $this->notFound();
        }

        $chapter = $chapters->find(Uuid::fromString($chapterId));

        // Rozdzial musi nalezec do wskazanego projektu - inaczej identyfikator
        // rozdzialu bylby furtka omijajaca kontrole wlasciciela.
        if (null === $chapter || !$chapter->getProject()->getId()->equals($project->getId())) {
            return $this->notFound();
        }

        try {
            $html = $renderer->render($project, $chapter);
        } catch (InvalidEpubException) {
            return ProblemResponse::create(Response::HTTP_NOT_FOUND, 'Nie udało się odczytać tego rozdziału.');
        }

        $response = new Response($html);
        $response->headers->set('Content-Type', 'application/xhtml+xml; charset=utf-8');

        return $response;
    }

    private function notFound(): Response
    {
        return ProblemResponse::create(Response::HTTP_NOT_FOUND, 'Nie znaleziono rozdziału.');
    }
}
