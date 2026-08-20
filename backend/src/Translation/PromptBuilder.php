<?php

declare(strict_types=1);

namespace App\Translation;

use App\Entity\Project;
use App\Entity\Segment;

/**
 * Assembles the two messages sent to the model. The instructions are in English
 * even when the target language is not: this is a prompt for the model, not
 * copy for a user, and small local models follow English instructions far more
 * reliably than translated ones.
 */
final readonly class PromptBuilder
{
    /**
     * Nazwy jezykow czytaja sie modelowi lepiej niz kody ISO. Kod spoza listy
     * jedzie do promptu doslownie - lepiej podac modelowi cokolwiek niz nic.
     *
     * @var array<string, string>
     */
    private const array LANGUAGE_NAMES = [
        'pl' => 'Polish',
        'en' => 'English',
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'uk' => 'Ukrainian',
        'cs' => 'Czech',
        'sk' => 'Slovak',
        'nl' => 'Dutch',
        'sv' => 'Swedish',
        'no' => 'Norwegian',
        'da' => 'Danish',
        'fi' => 'Finnish',
        'ja' => 'Japanese',
        'zh' => 'Chinese',
    ];

    public function build(Project $project, Segment $segment, ?Segment $previous): TranslationRequest
    {
        return new TranslationRequest(
            $project->getOllamaModel(),
            $this->systemPrompt($project),
            $this->userPrompt($segment, $previous),
        );
    }

    private function systemPrompt(Project $project): string
    {
        $lines = [
            \sprintf(
                'You are a literary translator. Translate the text the user sends into %s.',
                $this->languageName($project->getTargetLanguage()),
            ),
        ];

        $sourceLanguage = $project->getSourceLanguage();

        if (null !== $sourceLanguage && '' !== $sourceLanguage) {
            $lines[] = \sprintf('The source text is written in %s.', $this->languageName($sourceLanguage));
        }

        $lines[] = 'Rules you must follow exactly:';
        $lines[] = '- Reply with the translation only. No commentary, no explanations, no quotes around it.';
        $lines[] = '- Keep every formatting token such as [1], [/1] and [2/] exactly as it appears, with the same numbers. Move them with the words they mark, never rename, drop or add one.';
        $lines[] = '- Do not translate file names, URLs or e-mail addresses.';
        $lines[] = '- Preserve the tone and register of the original.';

        $customPrompt = $project->getCustomPrompt();

        if (null !== $customPrompt && '' !== trim($customPrompt)) {
            $lines[] = 'Additional instructions from the user:';
            $lines[] = trim($customPrompt);
        }

        return implode("\n", $lines);
    }

    private function userPrompt(Segment $segment, ?Segment $previous): string
    {
        $context = $this->context($previous);

        if (null === $context) {
            return $segment->getSourceText();
        }

        return $context."\n\n".implode("\n", [
            'Now translate this paragraph. Reply with the translation of this paragraph only:',
            $segment->getSourceText(),
        ]);
    }

    private function context(?Segment $previous): ?string
    {
        if (null === $previous) {
            return null;
        }

        $translation = $previous->getTranslatedText();

        if (null === $translation || '' === trim($translation)) {
            return null;
        }

        return implode("\n", [
            'For style and terminology reference only, here is the previous paragraph and how it was translated.',
            'Do not translate it again and do not repeat it in your reply.',
            'Previous source: '.$previous->getSourceText(),
            'Previous translation: '.$translation,
        ]);
    }

    private function languageName(string $code): string
    {
        return self::LANGUAGE_NAMES[strtolower($code)] ?? $code;
    }
}
