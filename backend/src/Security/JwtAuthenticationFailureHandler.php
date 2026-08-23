<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\ProblemResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Lexik's own failure handler answers {"code":401,"message":"Invalid
 * credentials."} - English text a user reads, in a shape no other error in this
 * API uses. The login form shows whatever "detail" holds, so the message
 * belongs here rather than in the frontend.
 */
final readonly class JwtAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Jeden komunikat na oba przypadki: rozroznienie "nie ma takiego konta"
        // od "zle haslo" mowi obcemu, kto ma tu konto.
        return ProblemResponse::create(Response::HTTP_UNAUTHORIZED, 'Nieprawidłowy e-mail lub hasło.');
    }
}
