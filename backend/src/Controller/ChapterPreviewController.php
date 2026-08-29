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
     * Applies only when someone opens this URL directly as a document. The
     * editor fetches the chapter with a token and injects it through srcdoc,
     * and a srcdoc document inherits the parent's policy instead of this
     * header - PreviewDecorator puts the policy that actually binds into the
     * document itself. Kept because the direct-navigation case is real and
     * costs nothing.
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
