<?php

declare(strict_types=1);

namespace App\Tests\State;

use App\State\UpdateSegmentProcessor;
use App\Translation\TranslationRejectedException;
use PHPUnit\Framework\TestCase;

/**
 * Covers the private detailFor() invariant directly: TranslationRejectionReason::Echo
 * must never reach this processor (see App\State\UpdateSegmentProcessor::process(),
 * which only ever calls TranslationValidator::validate(), never assertNotEchoed()).
 * Nothing in the public API can drive that branch - a real PATCH cannot produce an
 * Echo-reasoned rejection - so this reaches the method through reflection to prove
 * the branch fails loudly instead of silently mislabelling the cause.
 */
final class UpdateSegmentProcessorTest extends TestCase
{
    public function testEchoReasonReachingTheProcessorRaisesALogicException(): void
    {
        $processor = (new \ReflectionClass(UpdateSegmentProcessor::class))->newInstanceWithoutConstructor();

        $detailFor = new \ReflectionMethod(UpdateSegmentProcessor::class, 'detailFor');
        $detailFor->setAccessible(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TranslationRejectionReason::Echo must never reach UpdateSegmentProcessor');

        $detailFor->invoke($processor, TranslationRejectedException::echoedSource());
    }
}
