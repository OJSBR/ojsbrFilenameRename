<?php

/**
 * @file plugins/generic/ojsbrFilenameRename/tests/OjsbrFilenameRenameTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OjsbrFilenameRenameTest
 *
 * @brief The delivered file name: formats, languages, sanitization, the submission
 *        file asked for, the hook arguments and the settings form.
 */

namespace APP\plugins\generic\ojsbrFilenameRename\tests;

use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Request;
use APP\plugins\generic\ojsbrFilenameRename\OjsbrFilenameRenamePlugin;
use APP\plugins\generic\ojsbrFilenameRename\OjsbrFilenameRenameSettingsForm;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\context\Context;
use PKP\core\PKPComponentRouter;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\tests\PKPTestCase;

#[CoversClass(OjsbrFilenameRenamePlugin::class)]
#[CoversClass(OjsbrFilenameRenameSettingsForm::class)]
class OjsbrFilenameRenameTest extends PKPTestCase
{
    public function testNumbersOnlyFormat(): void
    {
        $this->assertSame('123-456.pdf', $this->plugin()->buildFilename(123, 456, '.pdf', true, 'pt_BR'));
        $this->assertSame('123-456', $this->plugin()->buildFilename(123, 456, '', true, 'en'));
    }

    public function testDescriptiveNameFollowsTheRequestedLanguage(): void
    {
        $plugin = $this->plugin();
        $this->assertSame('submission-123-file-456.pdf', $plugin->buildFilename(123, 456, '.pdf', false, 'en'));
        $this->assertSame('submissao-123-arquivo-456.pdf', $plugin->buildFilename(123, 456, '.pdf', false, 'pt_BR'));
        $this->assertSame('envio-123-fichero-456.pdf', $plugin->buildFilename(123, 456, '.pdf', false, 'es'));
    }

    public function testEveryShippedLanguageProducesAUsableName(): void
    {
        $plugin = $this->plugin();
        foreach (LocaleFilesTest::LOCALES as $locale) {
            $name = $plugin->buildFilename(987, 654, '.docx', false, $locale);
            $stem = substr($name, 0, -5);
            $this->assertTrue(str_ends_with($name, '.docx'), "Extension lost in {$locale}: {$name}");
            $this->assertTrue(OjsbrFilenameRenamePlugin::isValidStem($stem, 987, 654), "Unusable name in {$locale}: {$name}");
            $this->assertSame($stem, OjsbrFilenameRenamePlugin::sanitizeStem($stem), "Name not sanitized in {$locale}: {$name}");
            $this->assertStringNotContainsString('##', $name);
        }
    }

    public function testLanguageWithoutTranslationFallsBackToEnglish(): void
    {
        $this->assertSame('submission-1-file-2.pdf', $this->plugin()->buildFilename(1, 2, '.pdf', false, 'ku'));
    }

    public function testBrokenTranslationFallsBackToEnglishThenToNumbers(): void
    {
        // A translator who drops a placeholder must not break downloads.
        $plugin = $this->pluginTranslating(['pt_BR' => 'submissao-{$submissionId}', 'en' => 'submission-{$submissionId}-file-{$submissionFileId}']);
        $this->assertSame('submission-5-file-6.pdf', $plugin->buildFilename(5, 6, '.pdf', false, 'pt_BR'));

        $plugin = $this->pluginTranslating(['pt_BR' => '##key##', 'en' => '##key##']);
        $this->assertSame('5-6.pdf', $plugin->buildFilename(5, 6, '.pdf', false, 'pt_BR'));
    }

    public function testExtensionComesFromTheStoredPathFirst(): void
    {
        $this->assertSame('.pdf', OjsbrFilenameRenamePlugin::extractExtension('journals/1/articles/9/6aa72b.pdf', 'Paper.docx'));
        $this->assertSame('.tar.gz', OjsbrFilenameRenamePlugin::extractExtension('journals/1/articles/9/6aa72b.tar.gz', null));
        $this->assertSame('.docx', OjsbrFilenameRenamePlugin::extractExtension('journals/1/articles/9/noextension', 'Relatório final.DOCX'));
        $this->assertSame('', OjsbrFilenameRenamePlugin::extractExtension(null, 'no-extension'));
        $this->assertSame('', OjsbrFilenameRenamePlugin::extractExtension(null, 'weird.<script>'));
    }

    public function testStemIsSanitized(): void
    {
        $this->assertSame('etc-passwd-12', OjsbrFilenameRenamePlugin::sanitizeStem('../etc/passwd 12'));
        $this->assertSame('a-b-c', OjsbrFilenameRenamePlugin::sanitizeStem("  a\tb:*?\"<>| c\n"));
        $this->assertSame('投稿-1-ファイル-2', OjsbrFilenameRenamePlugin::sanitizeStem('投稿-1-ファイル-2'));
        $this->assertSame(OjsbrFilenameRenamePlugin::MAX_STEM_LENGTH, mb_strlen(OjsbrFilenameRenamePlugin::sanitizeStem(str_repeat('é', 400))));
    }

    public function testStemMustKeepBothNumbersAsWholeNumbers(): void
    {
        $this->assertTrue(OjsbrFilenameRenamePlugin::isValidStem('submission-12-file-34', 12, 34));
        $this->assertFalse(OjsbrFilenameRenamePlugin::isValidStem('submission-123-file-345', 12, 34));
        $this->assertFalse(OjsbrFilenameRenamePlugin::isValidStem('submission-12', 12, 34));
        $this->assertFalse(OjsbrFilenameRenamePlugin::isValidStem('', 12, 34));
    }

    public function testTheSubmissionFileThatWasAskedForNamesTheDownload(): void
    {
        // A review-stage copy shares the stored file with the original: the
        // name must carry the id the person clicked, not the newest copy.
        $rows = [
            (object) ['submission_file_id' => 44, 'submission_id' => 630],
            (object) ['submission_file_id' => 41, 'submission_id' => 630],
        ];
        $this->assertSame(41, OjsbrFilenameRenamePlugin::pickSubmissionFile($rows, [41])->submission_file_id);
        $this->assertSame(44, OjsbrFilenameRenamePlugin::pickSubmissionFile($rows, [44, 41])->submission_file_id);
        $this->assertSame(44, OjsbrFilenameRenamePlugin::pickSubmissionFile($rows, [999])->submission_file_id);
        $this->assertSame(44, OjsbrFilenameRenamePlugin::pickSubmissionFile($rows, [])->submission_file_id);
        $this->assertSame(null, OjsbrFilenameRenamePlugin::pickSubmissionFile([], [41]));
    }

    public function testRequestedIdsComeFromTheQueryAndTheLastPathArgument(): void
    {
        $request = new class () extends Request {
            public array $vars = [];

            public function getUserVar(string $key): mixed
            {
                return $this->vars[$key] ?? null;
            }
        };
        $pageRouter = new class () extends PageRouter {
            public array $args = [];

            public function getRequestedArgs(PKPRequest $request): array
            {
                return $this->args;
            }
        };
        $pageRouter->setApplication(Application::get());

        // The workflow goes through the component router, which has no path
        // arguments at all: asking it for them throws, and PKP then silently
        // delivers the original name. That is how the first build broke.
        $componentRouter = new PKPComponentRouter();
        $componentRouter->setApplication(Application::get());
        $request->setRouter($componentRouter);
        $request->vars = ['submissionFileId' => '48'];
        $this->assertSame([48], OjsbrFilenameRenamePlugin::requestedSubmissionFileIds($request));

        // article/download/{submissionId}/{galleyId}/{submissionFileId}
        $request->setRouter($pageRouter);
        $request->vars = [];
        $pageRouter->args = ['396', '4', '45'];
        $this->assertSame([45], OjsbrFilenameRenamePlugin::requestedSubmissionFileIds($request));

        $pageRouter->args = ['396', '4', 'some-slug'];
        $this->assertSame([], OjsbrFilenameRenamePlugin::requestedSubmissionFileIds($request));
    }

    public function testLanguageModeResolution(): void
    {
        $this->ensureRouter();
        $plugin = $this->plugin();
        $context = $this->context('es');

        $this->assertSame('es', $plugin->resolveLocale(OjsbrFilenameRenamePlugin::FILENAME_LOCALE_CONTEXT, $context));
        $current = \PKP\facades\Locale::getLocale();
        $this->assertSame($current, $plugin->resolveLocale(OjsbrFilenameRenamePlugin::FILENAME_LOCALE_USER, $context));
        $this->assertSame($current, $plugin->resolveLocale(null, $context));
        $this->assertSame($current, $plugin->resolveLocale('garbage', $context));
        $this->assertSame($current, $plugin->resolveLocale(OjsbrFilenameRenamePlugin::FILENAME_LOCALE_CONTEXT, null));
    }

    public function testFileOutsideSubmissionsKeepsItsName(): void
    {
        $this->ensureRouter();
        $plugin = $this->plugin();
        $filename = 'library-file.pdf';

        $unknownId = (int) DB::table('files')->max('file_id') + 1000;
        $this->assertSame(Hook::CONTINUE, $plugin->renameOnDownload('File::download', [(object) ['id' => $unknownId, 'path' => 'x.pdf'], &$filename, false]));
        $this->assertSame('library-file.pdf', $filename);

        $plugin->renameOnDownload('File::download', [null, &$filename, false]);
        $this->assertSame('library-file.pdf', $filename);
    }

    public function testSubmissionFileIsRenamedThroughTheHookArguments(): void
    {
        $row = DB::table('submission_files as sf')
            ->join('files as f', 'f.file_id', '=', 'sf.file_id')
            ->orderByDesc('sf.submission_file_id')
            ->first(['sf.file_id', 'sf.submission_file_id', 'sf.submission_id', 'f.path']);
        if (!$row) {
            $this->assertTrue(true);
            return;
        }

        // PKP passes the file row and the name by reference inside the array.
        $this->ensureRouter();
        $filename = 'Author Name - manuscript.docx';
        $args = [(object) ['id' => $row->file_id, 'path' => $row->path, 'mimetype' => null], &$filename, false];
        $this->plugin()->renameOnDownload('File::download', $args);

        $extension = OjsbrFilenameRenamePlugin::extractExtension($row->path, 'x.docx');
        $stem = substr($filename, 0, strlen($filename) - strlen($extension));
        $this->assertTrue(str_ends_with($filename, $extension));
        $this->assertTrue(OjsbrFilenameRenamePlugin::isValidStem($stem, (int) $row->submission_id, (int) $row->submission_file_id), "Not renamed: {$filename}");
        $this->assertStringNotContainsString('Author', $filename);
    }

    public function testSettingsFormSavesNormalizedValues(): void
    {
        $this->ensureRouter();
        $plugin = $this->recordingPlugin();
        $form = new OjsbrFilenameRenameSettingsForm($plugin, $this->context('pt_BR'));

        $form->setData('numbersOnly', '1');
        $form->setData('filenameLocale', OjsbrFilenameRenamePlugin::FILENAME_LOCALE_CONTEXT);
        $form->execute();

        $this->assertSame([true, 'bool'], $plugin->saved[OjsbrFilenameRenamePlugin::SETTING_NUMBERS_ONLY]);
        $this->assertSame(['context', 'string'], $plugin->saved[OjsbrFilenameRenamePlugin::SETTING_FILENAME_LOCALE]);
        $this->assertCount(2, $plugin->saved);
    }

    public function testSettingsFormDefaultsAndUnknownStoredValues(): void
    {
        $this->ensureRouter();
        $plugin = $this->recordingPlugin();

        $form = new OjsbrFilenameRenameSettingsForm($plugin, $this->context('pt_BR'));
        $form->initData();
        $this->assertSame('0', $form->getData('numbersOnly'));
        $this->assertSame('user', $form->getData('filenameLocale'));

        $plugin->stored = [OjsbrFilenameRenamePlugin::SETTING_NUMBERS_ONLY => true, OjsbrFilenameRenamePlugin::SETTING_FILENAME_LOCALE => 'garbage'];
        $form = new OjsbrFilenameRenameSettingsForm($plugin, $this->context('pt_BR'));
        $form->initData();
        $this->assertSame('1', $form->getData('numbersOnly'));
        $this->assertSame('user', $form->getData('filenameLocale'));
    }

    public function testSettingsFormRejectsValuesOutsideTheChoices(): void
    {
        $this->ensureRouter();
        $form = new OjsbrFilenameRenameSettingsForm($this->recordingPlugin(), $this->context('pt_BR'));
        $form->setData('numbersOnly', '2');
        $form->setData('filenameLocale', 'user');
        $form->validate(false);

        $this->assertTrue(isset($form->getErrorsArray()['numbersOnly']));
    }

    protected function plugin(): OjsbrFilenameRenamePlugin
    {
        $this->ensureRouter();
        $plugin = PluginRegistry::getPlugin('generic', 'ojsbrfilenamerenameplugin');
        if (!$plugin instanceof OjsbrFilenameRenamePlugin) {
            $plugin = PluginRegistry::loadPlugin('generic', 'ojsbrFilenameRename');
        }
        return $plugin;
    }

    protected function pluginTranslating(array $translations): OjsbrFilenameRenamePlugin
    {
        $this->plugin();
        return new class ($translations) extends OjsbrFilenameRenamePlugin {
            public function __construct(private array $translations)
            {
                parent::__construct();
            }

            protected function translateFilename(array $params, string $locale): string
            {
                return str_replace(['{$submissionId}', '{$submissionFileId}'], [$params['submissionId'], $params['submissionFileId']], $this->translations[$locale] ?? '##missing##');
            }
        };
    }

    protected function recordingPlugin(): OjsbrFilenameRenamePlugin
    {
        $this->plugin();
        return new class () extends OjsbrFilenameRenamePlugin {
            public array $stored = [];
            public array $saved = [];

            public function getSetting($contextId, $name)
            {
                return $this->stored[$name] ?? null;
            }

            public function updateSetting($contextId, $name, $value, $type = null)
            {
                $this->saved[$name] = [$value, $type];
            }

            public function getTemplateResource($template = null, $inCore = false)
            {
                return 'settings.tpl';
            }
        };
    }

    protected function context(string $primaryLocale): Context
    {
        // The journal of OJS or the press of OMP, whichever the suite runs on.
        $context = Application::getContextDAO()->newDataObject();
        $context->setId(1);
        $context->setData('primaryLocale', $primaryLocale);
        return $context;
    }

    /**
     * A command line request has no router, and PKP asks it for the context and
     * the locale. The page router answers "no context", the site level.
     */
    protected function ensureRouter(): void
    {
        $request = Application::get()->getRequest();
        if (!$request->getRouter()) {
            $router = new PageRouter();
            $router->setApplication(Application::get());
            $request->setRouter($router);
        }
    }

    public function testTheSiteLevelHasNoSettingsToOpen(): void
    {
        $request = new class () {
            public function getContext()
            {
                return null;
            }

            public function getUserVar($name)
            {
                return $name === 'verb' ? 'settings' : null;
            }

            public function getRouter()
            {
                throw new \RuntimeException('The site level must not build a settings URL.');
            }
        };
        $plugin = new class () extends OjsbrFilenameRenamePlugin {
            public function getEnabled($contextId = null)
            {
                return true;
            }
        };

        $this->assertSame([], array_filter($plugin->getActions($request, []), fn ($action) => $action->getId() === 'settings'));
        $this->expectExceptionMessage('Unhandled management action!');
        $plugin->manage([], $request);
    }
}
