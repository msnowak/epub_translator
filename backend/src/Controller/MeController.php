<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MeController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(#[CurrentUser] User $user): Response
    {
        return new JsonResponse([
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
