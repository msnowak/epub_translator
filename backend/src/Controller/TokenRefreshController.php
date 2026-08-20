<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ProblemResponse;
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
            return ProblemResponse::create(Response::HTTP_UNAUTHORIZED, 'Brak tokenu odświeżającego.');
        }

        try {
            [$user, $cookie] = $refreshTokenManager->rotate($plainToken);
        } catch (InvalidRefreshTokenException) {
            // This message reaches the UI, so it is Polish and deliberately does
            // not distinguish "unknown" from "expired".
            return ProblemResponse::create(Response::HTTP_UNAUTHORIZED, 'Sesja wygasła. Zaloguj się ponownie.');
        }

        $response = new JsonResponse(['token' => $jwtManager->create($user)]);
        $response->headers->setCookie($cookie);

        return $response;
    }
}
