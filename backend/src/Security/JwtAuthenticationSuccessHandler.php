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
 * Wraps Lexik's handler and attaches the refresh token cookie to the response.
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
        // assert() compiles out under zend.assertions=-1 (php.ini-production),
        // so this invariant must be a real throw rather than an assertion.
        if (!$user instanceof User) {
            throw new \LogicException(\sprintf('Expected the authenticated user to be an instance of %s, got %s.', User::class, get_debug_type($user)));
        }

        $response->headers->setCookie($this->refreshTokenManager->issue($user));

        return $response;
    }
}
