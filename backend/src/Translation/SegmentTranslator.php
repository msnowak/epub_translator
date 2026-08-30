<?php

declare(strict_types=1);

namespace App\Translation;

use App\Entity\Segment;
use App\Entity\SegmentStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Translates exactly one segment and leaves it in a final state: translated, or
 * failed once its attempt budget runs out. The chain handler stays free of the
 * retry rule, and this class stays free of Doctrine and messaging.
 */
final readonly class SegmentTranslator
{
    public function __construct(
        private PromptBuilder $promptBuilder,
        private TranslationEngineInterface $engine,
        private TranslationValidator $validator,
        #[Autowire('%env(int:MAX_TRANSLATION_ATTEMPTS)%')]
        private int $maxAttempts,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws TranslationEngineException gdy silnik jest nieosiagalny - segment zostaje nietkniety,
     *                                    zeby wznowienie moglo sprobowac jeszcze raz
     */
    public function translate(Segment $segment, ?Segment $previous): void
    {
        $request = $this->promptBuilder->build($segment->getProject(), $segment, $previous);
        $lastRejection = 'Model nie zwrócił poprawnego tłumaczenia tego akapitu.';

        while ($segment->getAttempts() < $this->maxAttempts) {
            // Wyjatek silnika leci wyzej, zanim policzymy probe: proba to
            // odpowiedz modelu, nie nieudana proba polaczenia.
            $translation = $this->engine->translate($request);

            $segment->incrementAttempts();

            try {
                $this->validator->validate($segment->getSourceText(), $translation);
                // Echo jest zawodnoscia modelu, nie problemem integralnosci -
                // dotyczy wylacznie tej sciezki, wiec ta metoda nie jest
                // wolana z UpdateSegmentProcessor.
                $this->validator->assertNotEchoed($segment->getSourceText(), $translation);
            } catch (TranslationRejectedException $exception) {
                // Powod techniczny idzie do logu, bo bez niego segment konczacy
                // jako failed jest nie do zdiagnozowania: nie wiadomo, czy model
                // zgubil zeton, wymyslil nowy, czy oddal oryginal bez zmian.
                $this->logger->notice('Translation of segment {segment} rejected on attempt {attempt}: {reason}', [
                    'segment' => (string) $segment->getId(),
                    'attempt' => $segment->getAttempts(),
                    'reason' => $exception->getMessage(),
                ]);

                // Uzytkownikowi "token 2 closes out of order" nie pomoze -
                // jedyne sensowne akcje to ponowienie albo reczna poprawka
                // w edytorze.
                $lastRejection = \sprintf(
                    'Model nie zwrócił poprawnego tłumaczenia tego akapitu (%d prób).',
                    $segment->getAttempts(),
                );

                continue;
            }

            $segment->setTranslatedText($translation);
            $segment->setStatus(SegmentStatus::Translated);
            $segment->setErrorMessage(null);

            return;
        }

        $segment->setStatus(SegmentStatus::Failed);
        $segment->setErrorMessage($lastRejection);
    }
}
