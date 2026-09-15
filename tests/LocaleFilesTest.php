<?php

/**
 * @file plugins/generic/ojsbrFilenameRename/tests/LocaleFilesTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleFilesTest
 *
 * @brief Translations. OJS 3.4 has no locale fallback: a key missing from a
 *        locale is rendered as ##key##, so an incomplete file is worse than none.
 */

namespace APP\plugins\generic\ojsbrFilenameRename\tests;

class LocaleFilesTest extends TestCase
{
    /** Locale codes shipped by the plugin, using the OJS 3.4 codes. */
    public const LOCALES = [
        'ar', 'az', 'bg', 'ca', 'cs', 'da', 'de', 'el', 'en', 'es', 'eu', 'fa', 'fi', 'fr_FR', 'fr_CA',
        'gl', 'hu', 'hy', 'id', 'it', 'ja', 'ka', 'mk', 'ms', 'nb', 'nl', 'pl', 'pt_PT', 'pt_BR',
        'ro', 'ru', 'sl', 'sr@latin', 'sv', 'tr', 'uk', 'vi', 'zh_CN',
    ];

    /** Locales reviewed by a fluent speaker; every other one is marked fuzzy. */
    public const REVIEWED = ['en', 'pt_PT', 'pt_BR', 'es', 'ca', 'gl', 'fr_FR', 'fr_CA', 'it', 'de', 'nl'];

    /** Languages written in the Latin script, whose file names must be plain ASCII. */
    public const LATIN_SCRIPT = [
        'az', 'ca', 'cs', 'da', 'de', 'en', 'es', 'eu', 'fi', 'fr_FR', 'fr_CA', 'gl', 'hu', 'id', 'it',
        'ms', 'nb', 'nl', 'pl', 'pt_PT', 'pt_BR', 'ro', 'sl', 'sr@latin', 'sv', 'tr', 'vi',
    ];

    /** Placeholders each key must keep, exactly once. */
    public const PLACEHOLDERS = [
        'settings.format.descriptive' => ['{$example}'],
        'settings.format.numbersOnly' => ['{$example}'],
        'settings.language.context' => ['{$language}', '{$example}'],
        'filename.descriptive' => ['{$submissionId}', '{$submissionFileId}'],
    ];

    public const PREFIX = 'plugins.generic.ojsbrFilenameRename.';

    protected function localeDir(): string
    {
        return dirname(__DIR__) . '/locale';
    }

    /** @return array<string, PoFile> */
    protected function files(): array
    {
        $files = [];
        foreach (self::LOCALES as $locale) {
            $path = $this->localeDir() . "/{$locale}/locale.po";
            if (is_file($path)) {
                $files[$locale] = new PoFile($path);
            }
        }
        return $files;
    }

    public function testShipsExactlyTheSupportedLocaleCodes(): void
    {
        $dirs = array_map('basename', glob($this->localeDir() . '/*', GLOB_ONLYDIR) ?: []);
        sort($dirs);
        $expected = self::LOCALES;
        sort($expected);

        // The OJS 3.5 codes (fr, pt, nb_NO, sr_Latn, zh_Hans) do not exist in
        // OJS 3.4 and would silently never load on this branch.
        $this->assertSame($expected, $dirs);
    }

    public function testEveryLocaleHasExactlyTheKeysOfTheEnglishMaster(): void
    {
        $files = $this->files();
        $master = array_keys($files['en']->entries);
        $this->assertCount(11, $master);

        foreach ($files as $locale => $file) {
            $this->assertSame($master, array_keys($file->entries), "Keys of {$locale} differ from en.");
        }
    }

    public function testNoTranslationIsEmpty(): void
    {
        foreach ($this->files() as $locale => $file) {
            foreach ($file->entries as $key => $value) {
                $this->assertNotEmpty(trim($value), "Empty translation for {$key} in {$locale}.");
            }
        }
    }

    public function testHeaderDeclaresTheLocaleAndTheTeam(): void
    {
        foreach ($this->files() as $locale => $file) {
            $this->assertStringContainsString("Language: {$locale}\n", $file->header, "Wrong Language header in {$locale}.");
            $this->assertStringContainsString("Last-Translator: OJSBR\n", $file->header, "Missing Last-Translator in {$locale}.");
            $this->assertStringContainsString("Language-Team: OJSBR\n", $file->header, "Missing Language-Team in {$locale}.");
        }
    }

    public function testUnreviewedLocalesAreFuzzyAndReviewedOnesAreNot(): void
    {
        foreach (self::LOCALES as $locale) {
            $source = (string) file_get_contents($this->localeDir() . "/{$locale}/locale.po");
            $fuzzy = substr_count($source, "#, fuzzy\n");
            $expected = in_array($locale, self::REVIEWED, true) ? 0 : 11;
            $this->assertSame($expected, $fuzzy, "Unexpected number of fuzzy entries in {$locale}.");
        }
    }

    public function testPlaceholdersAreKept(): void
    {
        foreach ($this->files() as $locale => $file) {
            foreach (self::PLACEHOLDERS as $key => $placeholders) {
                $value = $file->entries[self::PREFIX . $key];
                foreach ($placeholders as $placeholder) {
                    $this->assertSame(1, substr_count($value, $placeholder), "{$placeholder} must appear once in {$key} ({$locale}).");
                }
                // "count" is reserved by {translate} in PKP 3.4 (pluralization).
                $this->assertStringNotContainsString('{$count}', $value);
            }
        }
    }

    public function testTranslationsCarryNoMarkup(): void
    {
        foreach ($this->files() as $locale => $file) {
            foreach ($file->entries as $key => $value) {
                $this->assertSame(strip_tags($value), $value, "Markup in {$key} ({$locale}).");
            }
        }
    }

    public function testFileNamePatternIsSafeForEveryFileSystem(): void
    {
        foreach ($this->files() as $locale => $file) {
            $pattern = $file->entries[self::PREFIX . 'filename.descriptive'];
            $words = str_replace(['{$submissionId}', '{$submissionFileId}'], '', $pattern);

            $this->assertSame(0, preg_match('/[\s\/\\\\:*?"<>|#%.]/u', $words), "Unsafe character in the file name of {$locale}.");
            $this->assertSame($words, mb_strtolower($words), "The file name of {$locale} must be lowercase.");
            if (in_array($locale, self::LATIN_SCRIPT, true)) {
                $this->assertSame(1, preg_match('/^[a-z0-9-]+$/', $words), "The file name of {$locale} must be plain ASCII (no diacritics).");
            }
        }
    }
}
