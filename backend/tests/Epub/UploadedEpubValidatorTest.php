<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Epub\InvalidEpubException;
use App\Epub\UploadedEpubValidator;
use App\Tests\Support\EpubBuilder;
use PHPUnit\Framework\TestCase;

final class UploadedEpubValidatorTest extends TestCase
{
    public function testAcceptsValidEpub(): void
    {
        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Hello</p>')->build();

        (new UploadedEpubValidator())->validate($path);

        $this->expectNotToPerformAssertions();
    }

    public function testRejectsFileThatIsNotAZip(): void
    {
        $path = EpubBuilder::create()->corrupted()->build();

        $this->expectException(InvalidEpubException::class);

        (new UploadedEpubValidator())->validate($path);
    }

    public function testRejectsZipWithoutContainerXml(): void
    {
        $path = EpubBuilder::create()
            ->withoutContainerXml()
            ->withChapter('ch1.xhtml', '<p>Hello</p>')
            ->build();

        $this->expectException(InvalidEpubException::class);

        (new UploadedEpubValidator())->validate($path);
    }
}
