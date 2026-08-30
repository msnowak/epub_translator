<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ProblemResponse;
use App\Ollama\OllamaClient;
use App\Ollama\OllamaUnavailableException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OllamaModelsController
{
    #[Route('/api/ollama/models', name: 'api_ollama_models', methods: ['GET'])]
    public function __invoke(OllamaClient $client, TranslatorInterface $translator): Response
    {
        try {
            return new JsonResponse(['models' => $client->listModels()]);
        } catch (OllamaUnavailableException) {
            // This message is rendered in the project wizard, translated per request.
            return ProblemResponse::create(
                Response::HTTP_SERVICE_UNAVAILABLE,
                $translator->trans('ollama.unreachable'),
            );
        }
    }
}
