<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ProjectStatus;
use PHPUnit\Framework\TestCase;

final class ProjectStatusTest extends TestCase
{
    public function testOnlyReadyAndCancelledProjectsStart(): void
    {
        self::assertTrue(ProjectStatus::Ready->canStart());
        self::assertTrue(ProjectStatus::Cancelled->canStart());

        self::assertFalse(ProjectStatus::Parsing->canStart());
        self::assertFalse(ProjectStatus::Translating->canStart());
        self::assertFalse(ProjectStatus::Paused->canStart());
        self::assertFalse(ProjectStatus::Completed->canStart());
    }

    public function testOnlyATranslatingProjectPauses(): void
    {
        self::assertTrue(ProjectStatus::Translating->canPause());

        self::assertFalse(ProjectStatus::Paused->canPause());
        self::assertFalse(ProjectStatus::Ready->canPause());
    }

    public function testOnlyAPausedProjectResumes(): void
    {
        self::assertTrue(ProjectStatus::Paused->canResume());

        self::assertFalse(ProjectStatus::Translating->canResume());
        self::assertFalse(ProjectStatus::Ready->canResume());
    }

    public function testRunningAndPausedProjectsCancel(): void
    {
        self::assertTrue(ProjectStatus::Translating->canCancel());
        self::assertTrue(ProjectStatus::Paused->canCancel());

        self::assertFalse(ProjectStatus::Ready->canCancel());
        self::assertFalse(ProjectStatus::Completed->canCancel());
    }

    public function testFailedSegmentsAreRetriedOnceTheRunIsOver(): void
    {
        self::assertTrue(ProjectStatus::CompletedWithErrors->canRetryFailed());
        self::assertTrue(ProjectStatus::Paused->canRetryFailed());
        self::assertTrue(ProjectStatus::Cancelled->canRetryFailed());

        self::assertFalse(ProjectStatus::Translating->canRetryFailed());
        self::assertFalse(ProjectStatus::Parsing->canRetryFailed());
    }

    public function testEverythingWithAParsedStructureCanBeDownloaded(): void
    {
        self::assertTrue(ProjectStatus::Ready->canDownload());
        self::assertTrue(ProjectStatus::Translating->canDownload());
        self::assertTrue(ProjectStatus::Paused->canDownload());
        self::assertTrue(ProjectStatus::Completed->canDownload());
        self::assertTrue(ProjectStatus::CompletedWithErrors->canDownload());
        self::assertTrue(ProjectStatus::Cancelled->canDownload());

        // Parsing nie ma jeszcze rozdzialow, failed nie ma czytelnego pliku.
        self::assertFalse(ProjectStatus::Parsing->canDownload());
        self::assertFalse(ProjectStatus::Failed->canDownload());
    }
}
