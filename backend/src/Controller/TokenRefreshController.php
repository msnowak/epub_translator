<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\InvalidRefreshTokenException;
use App\Security\RefreshTokenManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TokenRefreshController
{
    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function __invoke(
        Request $request,
        RefreshTokenManager $refreshTokenManager,
        JWTTokenManagerInterface $jwtManager,
    ): Response {
        $plainToken = $request->cookies->get(RefreshTokenManager::COOKIE_NAME);

        if (null === $plainToken || '' === $plainToken) {
            return new JsonResponse(['message' => 'Brak tokenu odświeżającego.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            [$user, $cookie] = $refreshTokenManager->rotate($plainToken);
        } catch (InvalidRefreshTokenException) {
            // Komunikat trafia do interfejsu, wiec po polsku i bez szczegolow
            // rozrozniajacych "nieznany" od "wygasly".
            return new JsonResponse(['message' => 'Sesja wygasła. Zaloguj się ponownie.'], Response::HTTP_UNAUTHORIZED);
        }

        $response = new JsonResponse(['token' => $jwtManager->create($user)]);
        $response->headers->setCookie($cookie);

        return $response;
    }
}
