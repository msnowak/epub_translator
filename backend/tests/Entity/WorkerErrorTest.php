<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\WorkerError;
use PHPUnit\Framework\TestCase;

final class WorkerErrorTest extends TestCase
{
    /**
     * Ten test istnieje, bo druga polowa tego kontraktu mieszka po drugiej
     * stronie sieci: frontend/src/features/projects/workerError.ts trzyma
     * mape KEYS z tymi samymi czterema stringami, wpisana recznie i niczym
     * nie polaczona z tym enumem. Zmiana wartosci backing tutaj albo zapomniany
     * dopisek tam nie wywoluje zadnego bledu typow - kod po prostu przestaje
     * byc rozpoznawany i uzytkownik dostaje puste miejsce zamiast komunikatu.
     * Ten test pilnuje wartosci literalnych (nie tozsamosci case'ow enuma) i
     * pelnego zbioru - dodanie piatego przypadku tez ma go wywalic, zeby ktos
     * zajrzal do pliku workerError.ts po drugiej stronie.
     */
    public function testBackingValuesMatchTheFrontendContract(): void
    {
        $values = array_map(
            static fn (WorkerError $case): string => $case->value,
            WorkerError::cases(),
        );

        self::assertSame(
            [
                'epub_unreadable',
                'ollama_unreachable_project',
                'ollama_unreachable_segment',
                'model_invalid_translation',
            ],
            $values,
        );
    }
}
