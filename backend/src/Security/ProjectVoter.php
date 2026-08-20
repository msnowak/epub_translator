<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Druga warstwa obok OwnerExtension. Rozszerzenie ukrywa cudze projekty
 * w zapytaniach; voter pilnuje operacji, ktore dostaja obiekt inna droga.
 *
 * @extends Voter<string, Project>
 */
final class ProjectVoter extends Voter
{
    public const string VIEW = 'PROJECT_VIEW';
    public const string EDIT = 'PROJECT_EDIT';
    public const string DELETE = 'PROJECT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // supports() juz sprawdzil, ze $subject to Project.
        return $subject->getOwner()->getId()->equals($user->getId());
    }
}
