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
    /**
     * The chapter is book content served as a document from our own origin, so
     * it needs a policy of its own. It cannot be the one the assets endpoint
     * uses: the preview has to load the book's images and stylesheets, which
     * come back from that very endpoint. Nothing here grants scripts anything -
     * an omitted directive falls back to default-src 'none' - and
     * allow-same-origin is what keeps 'self' meaningful under the sandbox.
     */
    private const string CONTENT_SECURITY_POLICY = "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; base-uri 'none'; form-action 'none'; sandbox allow-same-origin";

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
        $response = $this->respond($id, $chapterId, $security, $projects, $chapters, $renderer);

        // Naglowki ida na kazda odpowiedz, takze bledna: sciezka wyjscia
        // z kontrolera nie moze decydowac o tym, na jakich zasadach tresc
        // z ksiazki laduje w przegladarce.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);

        return $response;
    }

    private function respond(
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
