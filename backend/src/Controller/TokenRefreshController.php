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
use Symfony\Contracts\Translation\TranslatorInterface;

final class TokenRefreshController
{
    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        RefreshTokenManager $refreshTokenManager,
        JWTTokenManagerInterface $jwtManager,
        TranslatorInterface $translator,
    ): Response {
        $plainToken = $request->cookies->get(RefreshTokenManager::COOKIE_NAME);

        if (null === $plainToken || '' === $plainToken) {
            return ProblemResponse::create(Response::HTTP_UNAUTHORIZED, $translator->trans('token.refresh_missing'));
        }

        try {
            [$user, $cookie] = $refreshTokenManager->rotate($plainToken);
        } catch (InvalidRefreshTokenException) {
            // This message reaches the UI, translated per request, and
            // deliberately does not distinguish "unknown" from "expired".
            return ProblemResponse::create(Response::HTTP_UNAUTHORIZED, $translator->trans('token.session_expired'));
        }

        $response = new JsonResponse(['token' => $jwtManager->create($user)]);
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/api/token/refresh', name: 'api_token_logout', methods: ['DELETE'])]
    public function logout(Request $request, RefreshTokenManager $refreshTokenManager): Response
    {
        // Ciasteczko ma path=/api/token/refresh, wiec wylogowanie musi siedziec
        // pod tym samym adresem - pod innym przegladarka po prostu by go nie
        // przyslala i nie byloby czego kasowac.
        $cookie = $refreshTokenManager->revoke($request->cookies->get(RefreshTokenManager::COOKIE_NAME));

        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($cookie);

        return $response;
    }
}
