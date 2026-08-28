<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Six endpoints are plain Symfony controllers - the ones that serve bytes and
 * the ones that predate the resources - so API Platform knows nothing about
 * them and the document described half the API. This adds them by hand.
 */
#[AsDecorator('api_platform.openapi.factory')]
final readonly class UndocumentedRoutesFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();

        $paths->addPath('/api/projects/{id}/preview/{chapterId}', new PathItem(
            get: new Operation(
                operationId: 'getChapterPreview',
                tags: ['Chapter'],
                responses: [
                    '200' => new Response('Rozdział jako XHTML gotowy do wyświetlenia w ramce podglądu.'),
                    '404' => new Response('Nie znaleziono rozdziału.'),
                ],
                summary: 'Renders a chapter with its translations for the editor preview.',
                description: 'Serves book content from the API origin: link targets move to data-epub-href and the document carries its own content security policy.',
                parameters: [
                    new Parameter('id', 'path', 'Identyfikator projektu.', true),
                    new Parameter('chapterId', 'path', 'Identyfikator rozdziału.', true),
                ],
            ),
        ));

        $paths->addPath('/api/projects/{id}/assets/{path}', new PathItem(
            get: new Operation(
                operationId: 'getProjectAsset',
                tags: ['Project'],
                responses: [
                    '200' => new Response('Bajty zasobu z archiwum EPUB.'),
                    '403' => new Response('Nieprawidłowy podpis adresu zasobu.'),
                    '404' => new Response('Nie znaleziono zasobu.'),
                ],
                summary: 'Serves one asset out of the uploaded EPUB.',
                description: 'The only endpoint outside the JWT firewall: the browser loads it from an iframe without an Authorization header. Guarded by an HMAC signature in the URL and by a check against the book manifest.',
                parameters: [
                    new Parameter('id', 'path', 'Identyfikator projektu.', true),
                    new Parameter('path', 'path', 'Ścieżka zasobu wewnątrz archiwum.', true),
                    new Parameter('t', 'query', 'Podpis adresu.', true),
                ],
            ),
        ));

        $paths->addPath('/api/projects/{id}/download', new PathItem(
            get: new Operation(
                operationId: 'downloadTranslatedEpub',
                tags: ['Project'],
                responses: [
                    '200' => new Response('Przetłumaczony plik EPUB.'),
                    '404' => new Response('Nie znaleziono projektu.'),
                    '409' => new Response('Ten projekt nie ma jeszcze książki do pobrania.'),
                ],
                summary: 'Downloads the translated book as an EPUB file.',
                description: 'Copies the original archive and replaces only chapter and package entries, so images and fonts pass through untouched.',
                parameters: [new Parameter('id', 'path', 'Identyfikator projektu.', true)],
            ),
        ));

        $paths->addPath('/api/me', new PathItem(
            get: new Operation(
                operationId: 'getCurrentUser',
                tags: ['User'],
                responses: [
                    '200' => new Response('Dane zalogowanego użytkownika.'),
                    '401' => new Response('Brak ważnego tokenu.'),
                ],
                summary: 'Returns the authenticated user.',
                description: 'Used by the SPA to restore a session after a reload.',
            ),
        ));

        $paths->addPath('/api/token/refresh', new PathItem(
            post: new Operation(
                operationId: 'refreshToken',
                tags: ['User'],
                responses: [
                    '200' => new Response('Nowy token dostępu.'),
                    '401' => new Response('Nieprawidłowy lub zużyty token odświeżania.'),
                ],
                summary: 'Rotates the refresh cookie and mints a new access token.',
                description: 'The refresh token travels in an HttpOnly cookie scoped to this path; each use consumes it and issues a new one.',
            ),
            delete: new Operation(
                operationId: 'logout',
                tags: ['User'],
                responses: ['204' => new Response('Wylogowano.')],
                summary: 'Logs out, ending every session of that user.',
                description: 'Idempotent: logging out twice is not an error.',
            ),
        ));

        $paths->addPath('/api/ollama/models', new PathItem(
            get: new Operation(
                operationId: 'listOllamaModels',
                tags: ['Ollama'],
                responses: [
                    '200' => new Response('Lista modeli widocznych na serwerze Ollamy.'),
                    '503' => new Response('Serwer Ollamy jest nieosiągalny.'),
                ],
                summary: 'Lists the models the configured Ollama server offers.',
                description: 'Feeds the model picker in the project wizard.',
            ),
        ));

        return $openApi->withPaths($paths);
    }
}
