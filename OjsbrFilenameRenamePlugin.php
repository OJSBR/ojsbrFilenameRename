<?php

/**
 * @file plugins/generic/ojsbrFilenameRename/OjsbrFilenameRenamePlugin.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OjsbrFilenameRenamePlugin
 *
 * @ingroup plugins_generic_ojsbrFilenameRename
 *
 * @brief Renames the file delivered on download to a neutral, standardized
 *  name, without touching the stored file or the name shown in the editorial
 *  interface. The descriptive name is translatable, so it follows the language
 *  of the person downloading or the primary language of the journal.
 */

namespace APP\plugins\generic\ojsbrFilenameRename;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\core\JSONMessage;
use PKP\core\PKPPageRouter;
use PKP\core\PKPRequest;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class OjsbrFilenameRenamePlugin extends GenericPlugin
{
    /** Setting: deliver only numbers instead of the descriptive name. */
    public const SETTING_NUMBERS_ONLY = 'numbersOnly';

    /** Setting: which language the descriptive name is written in. */
    public const SETTING_FILENAME_LOCALE = 'filenameLocale';

    /** Descriptive name in the interface language of the person downloading. */
    public const FILENAME_LOCALE_USER = 'user';

    /** Descriptive name in the primary language of the journal. */
    public const FILENAME_LOCALE_CONTEXT = 'context';

    /** Locale key holding the descriptive name pattern. */
    public const FILENAME_KEY = 'plugins.generic.ojsbrFilenameRename.filename.descriptive';

    /** Longest file name stem delivered, in characters. */
    public const MAX_STEM_LENGTH = 150;

    /**
     * Register the plugin and, where it is enabled, its hook.
     *
     * @param string $category
     * @param string $path
     * @param null|int $mainContextId
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);
        // Downloads are always requests to a journal.
        if (!$success || Application::isUnderMaintenance() || !$this->getEnabled($mainContextId)) {
            return $success;
        }

        // Called by PKPFileService::download() right before the headers are
        // sent: Hook::call('File::download', [$file, &$filename, $inline]).
        Hook::add('File::download', [$this, 'renameOnDownload']);

        return $success;
    }

    /**
     * Name shown in the plugins list.
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.ojsbrFilenameRename.displayName');
    }

    /**
     * Description shown in the plugins list.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.ojsbrFilenameRename.description');
    }

    /**
     * Add the settings action to the plugin entry in the plugins list.
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$request->getContext() || !$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();
        array_unshift($actions, new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, [
                    'verb' => 'settings',
                    'plugin' => $this->getName(),
                    'category' => 'generic',
                ]),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        ));

        return $actions;
    }

    /**
     * Show and save the settings form.
     */
    public function manage($args, $request): JSONMessage
    {
        // The settings belong to a journal; there is nothing to configure site-wide.
        $context = $request->getContext();
        if ($request->getUserVar('verb') !== 'settings' || !$context) {
            return parent::manage($args, $request);
        }

        $form = new OjsbrFilenameRenameSettingsForm($this, $context);

        if ($request->getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
                $form->execute();
                return new JSONMessage(true);
            }
        } else {
            $form->initData();
        }

        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Replace the name of a submission file delivered on download.
     *
     * Files that do not belong to a submission (library files, issue galleys,
     * ...) are left untouched.
     *
     * @param array $args [stdClass $file, string &$filename, bool $inline]
     */
    public function renameOnDownload($hookName, $args): bool
    {
        $file = $args[0] ?? null;
        $fileId = is_object($file) ? (int) ($file->id ?? $file->file_id ?? 0) : 0;
        if (!$fileId) {
            return Hook::CONTINUE;
        }

        // One stored file may be shared by several submission files of the same
        // submission (copies between workflow stages).
        $rows = DB::table('submission_files')
            ->where('file_id', '=', $fileId)
            ->orderByDesc('submission_file_id')
            ->get(['submission_file_id', 'submission_id'])
            ->all();
        $request = Application::get()->getRequest();
        $row = self::pickSubmissionFile($rows, self::requestedSubmissionFileIds($request));
        if (!$row) {
            return Hook::CONTINUE;
        }

        $context = $request->getContext();
        $contextId = $context?->getId();
        $numbersOnly = $contextId ? (bool) $this->getSetting($contextId, self::SETTING_NUMBERS_ONLY) : false;
        $localeMode = $contextId ? $this->getSetting($contextId, self::SETTING_FILENAME_LOCALE) : null;

        $args[1] = $this->buildFilename(
            (int) $row->submission_id,
            (int) $row->submission_file_id,
            self::extractExtension($file->path ?? null, $args[1] ?? null),
            $numbersOnly,
            $this->resolveLocale($localeMode, $context)
        );

        return Hook::CONTINUE;
    }

    /**
     * Submission file ids the request names: the workflow passes
     * ?submissionFileId=, the article download route carries it in the path
     * (article/download/{submissionId}/{galleyId}/{submissionFileId}).
     *
     * @return int[]
     */
    public static function requestedSubmissionFileIds(PKPRequest $request): array
    {
        // Only the page router has path arguments; asking the component router
        // used by the workflow for them throws.
        $args = $request->getRouter() instanceof PKPPageRouter ? $request->getRequestedArgs() : [];
        $lastArg = (string) end($args);
        $ids = [(int) $request->getUserVar('submissionFileId'), ctype_digit($lastArg) ? (int) $lastArg : 0];
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Choose the submission file the person asked for among those sharing
     * the stored file; without a match, the newest one.
     *
     * @param object[] $rows Rows with submission_file_id and submission_id, newest first
     * @param int[] $requestedIds In order of preference
     */
    public static function pickSubmissionFile(array $rows, array $requestedIds): ?object
    {
        foreach ($requestedIds as $id) {
            foreach ($rows as $row) {
                if ((int) $row->submission_file_id === $id) {
                    return $row;
                }
            }
        }
        return $rows[0] ?? null;
    }

    /**
     * Build the delivered file name.
     *
     * @param string $extension Extension with its leading dot, or ''
     * @param ?string $locale Locale of the descriptive name; null = current locale
     */
    public function buildFilename(int $submissionId, int $submissionFileId, string $extension, bool $numbersOnly, ?string $locale = null): string
    {
        $numbers = $submissionId . '-' . $submissionFileId;
        if ($numbersOnly) {
            return $numbers . $extension;
        }

        $params = ['submissionId' => $submissionId, 'submissionFileId' => $submissionFileId];
        foreach (array_unique(array_filter([$locale ?? Locale::getLocale(), 'en'])) as $candidate) {
            $stem = self::sanitizeStem($this->translateFilename($params, $candidate));
            if (self::isValidStem($stem, $submissionId, $submissionFileId)) {
                return $stem . $extension;
            }
        }

        // No usable translation at all: never deliver "##key##".
        return $numbers . $extension;
    }

    /**
     * Translate the descriptive name pattern. A missing translation comes
     * back as "##key##", which the caller rejects.
     */
    protected function translateFilename(array $params, string $locale): string
    {
        return __(self::FILENAME_KEY, $params, $locale);
    }

    /**
     * Locale of the descriptive name for the given setting value.
     */
    public function resolveLocale(?string $localeMode, ?Context $context): string
    {
        if ($localeMode === self::FILENAME_LOCALE_CONTEXT && $context) {
            return $context->getPrimaryLocale();
        }
        return Locale::getLocale();
    }

    /**
     * Extension to keep, taken from the stored path first (it is generated by
     * the application) and from the offered name as a fallback.
     *
     * @return string The extension with its leading dot, or ''
     */
    public static function extractExtension(?string $path, ?string $filename): string
    {
        foreach ([$path, $filename] as $candidate) {
            if ($candidate && preg_match('/(?:\.tar)?\.[A-Za-z0-9]{1,10}$/', $candidate, $matches)) {
                return strtolower($matches[0]);
            }
        }
        return '';
    }

    /**
     * Turn a translated name into a safe file name stem: no path separators,
     * reserved or control characters, whitespace collapsed into hyphens.
     */
    public static function sanitizeStem(string $stem): string
    {
        $stem = preg_replace('/[\s\x00-\x1F\x7F\/\\\\:*?"<>|#%]+/u', '-', $stem) ?? '';
        $stem = preg_replace('/-{2,}/', '-', $stem) ?? '';
        $stem = trim($stem, '-. ');
        return mb_substr($stem, 0, self::MAX_STEM_LENGTH);
    }

    /**
     * A translated stem is only usable when it still carries both numbers.
     */
    public static function isValidStem(string $stem, int $submissionId, int $submissionFileId): bool
    {
        return $stem !== ''
            && preg_match('/(?<!\d)' . $submissionId . '(?!\d)/', $stem)
            && preg_match('/(?<!\d)' . $submissionFileId . '(?!\d)/', $stem);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\ojsbrFilenameRename\OjsbrFilenameRenamePlugin', '\OjsbrFilenameRenamePlugin');
}
