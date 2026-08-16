<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SmokeTest extends KernelTestCase
{
    public function testKernelBootsAndDatabaseIsReachable(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $result = $entityManager->getConnection()->fetchOne('SELECT 1');
        self::assertSame(1, (int) $result);
    }
}
