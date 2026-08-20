<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Translation\TranslationEngineInterface;
use App\Translation\TranslationRequest;

/**
 * Scripted engine: hands out the queued answers in order and repeats the last
 * one once the queue runs dry, so a test only has to script the calls it cares
 * about. No test ever touches the network.
 */
final class FakeTranslationEngine implements TranslationEngineInterface
{
    /** @var list<string> */
    private array $answers = [];

    private ?\Throwable $error = null;

    private int $callCount = 0;

    private ?TranslationRequest $lastRequest = null;

    public function answerWith(string ...$answers): void
    {
        $this->answers = array_values($answers);
        $this->error = null;
    }

    public function failWith(\Throwable $error): void
    {
        $this->error = $error;
    }

    public function callCount(): int
    {
        return $this->callCount;
    }

    public function lastRequest(): ?TranslationRequest
    {
        return $this->lastRequest;
    }

    public function translate(TranslationRequest $request): string
    {
        ++$this->callCount;
        $this->lastRequest = $request;

        if (null !== $this->error) {
            throw $this->error;
        }

        if ([] === $this->answers) {
            // Domyslka: zwroc cos, co przejdzie walidacje dla tekstu bez zetonow.
            return 'Przetłumaczony akapit numer '.$this->callCount.'.';
        }

        return \count($this->answers) > 1 ? (string) array_shift($this->answers) : $this->answers[0];
    }
}
