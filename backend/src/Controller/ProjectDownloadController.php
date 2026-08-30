<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Epub\InvalidEpubException;
use App\Export\TranslatedEpubBuilder;
use App\Http\ProblemResponse;
use App\Repository\ProjectRepository;
use App\Security\ProjectVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Hands over the translated book. The file is built for this one request and
 * deleted once it has been sent: there is nothing to invalidate, and every
 * download carries the corrections made a second earlier in the editor.
 */
final class ProjectDownloadController
{
    #[Route(
        '/api/projects/{id}/download',
        name: 'api_project_download',
        methods: ['GET'],
    )]
    public function __invoke(
        string $id,
        Security $security,
        ProjectRepository $projects,
        TranslatedEpubBuilder $builder,
        Filesystem $filesystem,
        TranslatorInterface $translator,
    ): Response {
        if (!Uuid::isValid($id)) {
            return $this->notFound($translator);
        }

        $project = $projects->find(Uuid::fromString($id));

        // Cudzy projekt dostaje 404, nie 403 - identyfikator nie ma
        // potwierdzac, ze taki projekt istnieje.
        if (null === $project || !$security->isGranted(ProjectVoter::VIEW, $project)) {
            return $this->notFound($translator);
        }

        if (!$project->getStatus()->canDownload()) {
            return ProblemResponse::create(
                Response::HTTP_CONFLICT,
                $translator->trans('download.nothing_yet'),
            );
        }

        try {
            $path = $builder->build($project);
        } catch (InvalidEpubException) {
            return ProblemResponse::create(
                Response::HTTP_NOT_FOUND,
                $translator->trans('download.assembly_failed'),
            );
        }

        try {
            $response = new BinaryFileResponse($path);
            $response->headers->set('Content-Type', 'application/epub+zip');
            $response->headers->set('Content-Disposition', $this->disposition($project));
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            // build() juz zwrocilo - bez tego catcha kazdy wyjatek miedzy tym
            // momentem a deleteFileAfterSend(true) osierocalby zbudowana
            // kopie ksiazki w katalogu tymczasowym.
            $this->discardFile($filesystem, $path);

            throw $exception;
        }

        return $response;
    }

    /**
     * Best-effort cleanup, like EpubWriter's own: the real exception is
     * already on its way to the caller and a failed delete must not replace it.
     */
    private function discardFile(Filesystem $filesystem, string $path): void
    {
        try {
            $filesystem->remove($path);
        } catch (IOException) {
            // celowo pomijamy - inaczej awaria sprzatania zaslonilaby powod,
            // dla ktorego w ogole tu jestesmy.
        }
    }

    private function disposition(Project $project): string
    {
        // Tytul projektu nie ma zadnego ograniczenia na "/" ani "\", a
        // HeaderUtils::makeDisposition() rzuca, gdy ktorykolwiek argument je
        // niesie - myslnik czyta sie lepiej niz zwykle wyciecie znaku.
        $title = str_replace(['/', '\\'], '-', $project->getTitle());
        $name = \sprintf('%s-%s.epub', $title, $project->getTargetLanguage());
        $fallback = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $name), '-.');

        // Fallback musi byc czystym ASCII bez ukosnikow - tytul cyrylica albo
        // po chinsku zostawilby po sobie sam ogryzek, wiec wtedy wolimy
        // nazwe neutralna niz dziwna.
        if (!str_ends_with($fallback, '.epub')) {
            $fallback = 'book.epub';
        }

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name, $fallback);
    }

    private function notFound(TranslatorInterface $translator): Response
    {
        return ProblemResponse::create(Response::HTTP_NOT_FOUND, $translator->trans('project.not_found'));
    }
}
