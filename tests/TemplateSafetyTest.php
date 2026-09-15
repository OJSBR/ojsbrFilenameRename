<?php

/**
 * @file plugins/generic/ojsbrFilenameRename/tests/TemplateSafetyTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemplateSafetyTest
 *
 * @brief Static checks on the settings template and the source files.
 */

namespace APP\plugins\generic\ojsbrFilenameRename\tests;

class TemplateSafetyTest extends TestCase
{
    protected function template(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/templates/settings.tpl');
    }

    public function testFormIsProtectedAgainstCsrf(): void
    {
        $this->assertStringContainsString('{csrf}', $this->template());
        $this->assertStringContainsString('AjaxFormHandler', $this->template());
    }

    public function testTemplateHasNoHardcodedText(): void
    {
        $text = preg_replace('/\{\*.*?\*\}/s', '', $this->template());
        $text = preg_replace('/<script\b.*?<\/script>/s', '', $text);
        $text = preg_replace('/\{[^{}]*\}/', '', $text);
        $text = trim(strip_tags($text));

        $this->assertSame('', $text, 'Visible text must come from locale keys.');
    }

    public function testRadioLabelsAreEscapedByTheCore(): void
    {
        // Labels are passed pre-translated with translate=false; the core
        // radioButton.tpl escapes them. Unescaped output would need |escape here.
        $core = dirname(__DIR__, 4) . '/lib/pkp/templates/form/radioButton.tpl';
        if (!is_file($core)) {
            $this->assertTrue(true);
            return;
        }
        $this->assertStringContainsString('{$FBV_label|escape}', (string) file_get_contents($core));
    }

    public function testSourceIsWrittenInEnglishWithTheStandardHeader(): void
    {
        foreach (array_merge(glob(dirname(__DIR__) . '/*.php'), glob(__DIR__ . '/*.php')) as $file) {
            $source = (string) file_get_contents($file);
            $this->assertStringContainsString('Copyright (c) 2026 OJSBR (https://ojsbr.com)', $source, basename($file) . ' lacks the header.');
            $this->assertSame(0, preg_match('/\b(arquivo|revista|configura[cç][aã]o|padr[aã]o)\b/iu', preg_replace('/\'[^\']*\'/', '', $source)), basename($file) . ' has Portuguese outside string literals.');
        }
    }
}
