<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<User, User>
 */
final readonly class RegisterUserProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<User, User> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @param mixed                $data
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        // assert() compiles out under zend.assertions=-1 (php.ini-production),
        // so these invariants must be real throws rather than assertions.
        if (!$data instanceof User) {
            throw new \LogicException(\sprintf('Expected data to be an instance of %s, got %s.', User::class, get_debug_type($data)));
        }

        $plainPassword = $data->getPlainPassword();
        if (null === $plainPassword) {
            throw new \LogicException('User has no plain password to hash - the registration payload must always provide one.');
        }

        $data->setPassword($this->passwordHasher->hashPassword($data, $plainPassword));
        $data->setPlainPassword(null);
        $data->setRoles(['ROLE_USER']);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
