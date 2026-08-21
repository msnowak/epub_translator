<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\User;
use App\Epub\BlockExtractor;
use App\Epub\ChapterComposer;
use App\Epub\InlineTokenizer;
use App\Epub\XhtmlDocument;
use PHPUnit\Framework\TestCase;

final class ChapterComposerTest extends TestCase
{
    public function testWritesTranslationIntoTheMatchingBlock(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>This is important.</p>'));

        $this->composer()->compose($document, [
            $this->segment(nodeIndex: 0, source: 'This is important.', translation: 'To jest ważne.'),
        ]);

        $saved = $xhtml->save($document);

        self::assertStringContainsString('To jest ważne.', $saved);
        self::assertStringNotContainsString('This is important.', $saved);
    }

    public function testExpandsTokensBackIntoMarkup(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>This is <em>important</em>.</p>'));

        $this->composer()->compose($document, [
            $this->segment(
                nodeIndex: 0,
                source: 'This is [1]important[/1].',
                translation: 'To jest [1]ważne[/1].',
                placeholders: ['1' => '<em>'],
            ),
        ]);

        self::assertStringContainsString('To jest <em>ważne</em>.', $xhtml->save($document));
    }

    public function testJoinsSubSegmentsInOrder(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>First sentence. Second sentence.</p>'));

        $this->composer()->compose($document, [
            // Celowo w odwrotnej kolejnosci - liczy sie subIndex, nie kolejnosc w tablicy.
            $this->segment(nodeIndex: 0, subIndex: 1, source: 'Second sentence.', translation: 'Drugie zdanie.'),
            $this->segment(nodeIndex: 0, subIndex: 0, source: 'First sentence.', translation: 'Pierwsze zdanie.'),
        ]);

        self::assertStringContainsString('Pierwsze zdanie. Drugie zdanie.', $xhtml->save($document));
    }

    public function testLeavesTheBlockAloneWhenASubSegmentIsMissing(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>First sentence. Second sentence.</p>'));

        $this->composer()->compose($document, [
            $this->segment(nodeIndex: 0, subIndex: 0, source: 'First sentence.', translation: 'Pierwsze zdanie.'),
            $this->segment(nodeIndex: 0, subIndex: 1, source: 'Second sentence.', translation: null, status: SegmentStatus::Pending),
        ]);

        $saved = $xhtml->save($document);

        // Akapit w polowie polski bylby gorszy niz nieprzetlumaczony.
        self::assertStringContainsString('First sentence. Second sentence.', $saved);
        self::assertStringNotContainsString('Pierwsze zdanie.', $saved);
    }

    public function testLeavesFailedSegmentsInTheOriginal(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>This is important.</p>'));

        $this->composer()->compose($document, [
            $this->segment(nodeIndex: 0, source: 'This is important.', translation: null, status: SegmentStatus::Failed),
        ]);

        self::assertStringContainsString('This is important.', $xhtml->save($document));
    }

    public function testEditedSegmentsCountAsTranslations(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>This is important.</p>'));

        $this->composer()->compose($document, [
            $this->segment(
                nodeIndex: 0,
                source: 'This is important.',
                translation: 'Poprawione ręcznie.',
                status: SegmentStatus::Edited,
            ),
        ]);

        self::assertStringContainsString('Poprawione ręcznie.', $xhtml->save($document));
    }

    public function testWritesEachBlockIntoItsOwnNode(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>One.</p><p>Two.</p><p>Three.</p>'));

        $this->composer()->compose($document, [
            $this->segment(nodeIndex: 2, source: 'Three.', translation: 'Trzy.'),
            $this->segment(nodeIndex: 0, source: 'One.', translation: 'Jeden.'),
        ]);

        $saved = $xhtml->save($document);

        self::assertStringContainsString('<p>Jeden.</p>', $saved);
        self::assertStringContainsString('<p>Two.</p>', $saved);
        self::assertStringContainsString('<p>Trzy.</p>', $saved);
    }

    public function testSkipsBlocksThatHaveNoSegment(): void
    {
        // Blok zawierajacy tylko obraz nie jest segmentem i ma przejsc bez zmian.
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p><img src="cover.png"/></p><p>Text.</p>'));

        $this->composer()->compose($document, [
            $this->segment(nodeIndex: 0, source: 'Text.', translation: 'Tekst.'),
        ]);

        $saved = $xhtml->save($document);

        self::assertStringContainsString('cover.png', $saved);
        self::assertStringContainsString('Tekst.', $saved);
    }

    public function testAnEmptySegmentListLeavesTheChapterUntouched(): void
    {
        $xhtml = new XhtmlDocument();
        $source = $this->chapter('<p>One.</p><p>Two.</p>');
        $document = $xhtml->load($source);

        $this->composer()->compose($document, []);

        self::assertStringContainsString('<p>One.</p>', $xhtml->save($document));
        self::assertStringContainsString('<p>Two.</p>', $xhtml->save($document));
    }

    private function composer(): ChapterComposer
    {
        $xhtml = new XhtmlDocument();

        return new ChapterComposer(new BlockExtractor($xhtml), new InlineTokenizer(), $xhtml);
    }

    /**
     * @param array<array-key, string> $placeholders
     */
    private function segment(
        int $nodeIndex,
        string $source,
        ?string $translation = null,
        int $subIndex = 0,
        array $placeholders = [],
        SegmentStatus $status = SegmentStatus::Translated,
    ): Segment {
        $user = new User();
        $user->setEmail('owner@example.com');
        $project = new Project($user, 'Książka', 'pl', 'llama3.1:8b', 'book.epub');

        $segment = new Segment(
            new Chapter($project, 0, 'OEBPS/ch1.xhtml'),
            $nodeIndex,
            $nodeIndex,
            $subIndex,
            $source,
            $placeholders,
        );
        $segment->setTranslatedText($translation);
        $segment->setStatus($status);

        return $segment;
    }

    private function chapter(string $body): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><head><title>T</title></head>'
            .'<body>'.$body.'</body></html>';
    }
}
