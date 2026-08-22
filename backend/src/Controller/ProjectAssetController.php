<?php

declare(strict_types=1);

namespace App\Controller;

use App\Epub\EpubReader;
use App\Epub\InvalidEpubException;
use App\Http\ProblemResponse;
use App\Preview\AssetPathResolver;
use App\Preview\AssetUrlSigner;
use App\Repository\ProjectRepository;
use App\Storage\ProjectStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The only endpoint outside the JWT firewall - see AssetUrlSigner for why.
 * Requires both a valid signature and a path the EPUB's manifest declares.
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
        ProjectRepository $projects,
        ProjectStorage $storage,
        EpubReader $reader,
    ): Response {
        $token = $request->query->get('t');

        if (!\is_string($token) || !$signer->isValid($id, $path, $token)) {
            return ProblemResponse::create(Response::HTTP_FORBIDDEN, 'Nieprawidłowy podpis adresu zasobu.');
        }

        if (!Uuid::isValid($id)) {
            return ProblemResponse::create(Response::HTTP_NOT_FOUND, 'Nie znaleziono zasobu.');
        }

        $project = $projects->find(Uuid::fromString($id));

        if (null === $project) {
            return ProblemResponse::create(Response::HTTP_NOT_FOUND, 'Nie znaleziono zasobu.');
        }

        try {
            $package = $reader->open($storage->path($project));
        } catch (InvalidEpubException) {
            return ProblemResponse::create(Response::HTTP_NOT_FOUND, 'Nie znaleziono zasobu.');
        }

        try {
            $resolved = $resolver->resolve($path, $package->manifestHrefs());

            if (null === $resolved) {
                return ProblemResponse::create(Response::HTTP_NOT_FOUND, 'Nie znaleziono zasobu.');
            }

            $response = new Response($package->read($resolved));
            $response->headers->set('Content-Type', $this->contentType($resolved));
            // Podpis i tak wygasa, a plik w ksiazce sie nie zmienia.
            $response->headers->set('Cache-Control', 'private, max-age=3600');

            return $response;
        } catch (InvalidEpubException) {
            return ProblemResponse::create(Response::HTTP_NOT_FOUND, 'Nie znaleziono zasobu.');
        } finally {
            // Wykona sie takze przy return powyzej.
            $package->close();
        }
    }

    private function contentType(string $path): string
    {
        return match (strtolower(pathinfo($path, \PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'css' => 'text/css',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'xhtml', 'html' => 'application/xhtml+xml',
            default => 'application/octet-stream',
        };
    }
}
