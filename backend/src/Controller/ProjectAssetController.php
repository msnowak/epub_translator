<?php

declare(strict_types=1);

namespace App\Controller;

use App\Epub\EpubReader;
use App\Epub\InvalidEpubException;
use App\Http\ProblemResponse;
use App\Preview\AssetPathResolver;
use App\Preview\AssetUrlSigner;
use App\Preview\StylesheetRewriter;
use App\Repository\ProjectRepository;
use App\Storage\ProjectStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The only endpoint outside the JWT firewall - see AssetUrlSigner for why.
 * Requires both a valid signature and a path the EPUB's manifest declares.
 * The bytes are the book's own, untouched by the preview's sanitisation, so
 * every response is hardened against being interpreted as a document on our
 * origin - a script served from here would sit next to the session.
 */
final class ProjectAssetController
{
    #[Route(
        '/api/projects/{id}/assets/{path}',
        name: 'api_project_asset',
        requirements: ['path' => '.+'],
        methods: ['GET'],
    )]
    public function __invoke(
        string $id,
        string $path,
        Request $request,
        AssetUrlSigner $signer,
        AssetPathResolver $resolver,
        StylesheetRewriter $stylesheetRewriter,
        ProjectRepository $projects,
        ProjectStorage $storage,
        EpubReader $reader,
        TranslatorInterface $translator,
    ): Response {
        $response = $this->respond(
            $id,
            $path,
            $request,
            $signer,
            $resolver,
            $stylesheetRewriter,
            $projects,
            $storage,
            $reader,
            $translator,
        );

        // Naglowki ida na kazda odpowiedz, takze bledna: sciezka wyjscia
        // nie moze decydowac o tym, czy tresc z ksiazki wykona sie na naszej
        // domenie. "sandbox" bez wartosci odbiera skryptom origin, a
        // default-src 'none' - dostep do czegokolwiek dalej.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");

        return $response;
    }

    private function respond(
        string $id,
        string $path,
        Request $request,
        AssetUrlSigner $signer,
        AssetPathResolver $resolver,
        StylesheetRewriter $stylesheetRewriter,
        ProjectRepository $projects,
        ProjectStorage $storage,
        EpubReader $reader,
        TranslatorInterface $translator,
    ): Response {
        $token = $request->query->get('t');

        if (!\is_string($token) || !$signer->isValid($id, $path, $token)) {
            return ProblemResponse::create(Response::HTTP_FORBIDDEN, $translator->trans('asset.invalid_signature'));
        }

        if (!Uuid::isValid($id)) {
            return ProblemResponse::create(Response::HTTP_NOT_FOUND, $translator->trans('asset.not_found'));
        }

        $project = $projects->find(Uuid::fromString($id));

        if (null === $project) {
            return ProblemResponse::create(Response::HTTP_NOT_FOUND, $translator->trans('asset.not_found'));
        }

        try {
            $package = $reader->open($storage->path($project));
        } catch (InvalidEpubException) {
            return ProblemResponse::create(Response::HTTP_NOT_FOUND, $translator->trans('asset.not_found'));
        }

        try {
            $resolved = $resolver->resolve($path, $package->manifestHrefs());

            if (null === $resolved) {
                return ProblemResponse::create(Response::HTTP_NOT_FOUND, $translator->trans('asset.not_found'));
            }

            $content = $package->read($resolved);

            // Podpisujemy dopiero to, co manifest juz przepuscil: wywolanie
            // StylesheetRewriter idzie po resolve() powyzej, nigdy przed -
            // odwrotna kolejnosc podpisywalaby sciezke, ktorej ksiazka
            // wcale nie deklaruje.
            if ('css' === $this->extension($resolved)) {
                $content = $stylesheetRewriter->rewrite($content, $id, $resolved);
            }

            $response = new Response($content);
            $response->headers->set('Content-Type', $this->contentType($resolved));
            // Podpis i tak wygasa, a plik w ksiazce sie nie zmienia.
            $response->headers->set('Cache-Control', 'private, max-age=3600');

            return $response;
        } catch (InvalidEpubException) {
            return ProblemResponse::create(Response::HTTP_NOT_FOUND, $translator->trans('asset.not_found'));
        } finally {
            // Wykona sie takze przy return powyzej.
            $package->close();
        }
    }

    private function extension(string $path): string
    {
        return strtolower(pathinfo($path, \PATHINFO_EXTENSION));
    }

    private function contentType(string $path): string
    {
        return match ($this->extension($path)) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            // SVG musi zostac obrazem, zeby renderowalo sie w <img>; skrypt
            // w srodku unieszkodliwia polityka CSP powyzej.
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'css' => 'text/css',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            // Plik rozdzialu tez jest w manifescie, wiec da sie o niego
            // poprosic. Wydany jako dokument wykonalby swoje skrypty na
            // naszej domenie - podglad nigdy nie przechodzi ta droga.
            'xhtml', 'html' => 'text/plain; charset=utf-8',
            default => 'application/octet-stream',
        };
    }
}
