<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Owija handler Lexika i dokleja do odpowiedzi cookie z tokenem odswiezajacym.
 */
final readonly class JwtAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private AuthenticationSuccessHandlerInterface $decorated,
        private RefreshTokenManager $refreshTokenManager,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $response = $this->decorated->onAuthenticationSuccess($request, $token);

        if (!$response instanceof Response) {
            // The interface allows null, but Lexik's own handler always returns
            // a Response - this guard exists to satisfy static analysis.
            throw new \LogicException('Expected the decorated success handler to return a Response.');
        }

        $user = $token->getUser();
        \assert($user instanceof User);

        $response->headers->setCookie($this->refreshTokenManager->issue($user));

        return $response;
    }
}
