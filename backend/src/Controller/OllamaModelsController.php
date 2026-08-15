<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ollama\OllamaClient;
use App\Ollama\OllamaUnavailableException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OllamaModelsController
{
    #[Route('/api/ollama/models', name: 'api_ollama_models', methods: ['GET'])]
    public function __invoke(OllamaClient $client): Response
    {
        try {
            return new JsonResponse(['models' => $client->listModels()]);
        } catch (OllamaUnavailableException) {
            // Komunikat trafia do kreatora projektu w interfejsie, wiec po polsku.
            return new JsonResponse(
                ['message' => 'Nie udało się połączyć z serwerem Ollama. Sprawdź konfigurację połączenia.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
