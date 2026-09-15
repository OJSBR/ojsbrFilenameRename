<?php

/**
 * @file plugins/generic/ojsbrFilenameRename/OjsbrFilenameRenameSettingsForm.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OjsbrFilenameRenameSettingsForm
 *
 * @ingroup plugins_generic_ojsbrFilenameRename
 *
 * @brief Per-journal settings: file name format and language of the
 *  descriptive name.
 */

namespace APP\plugins\generic\ojsbrFilenameRename;

use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorInSet;
use PKP\form\validation\FormValidatorPost;

class OjsbrFilenameRenameSettingsForm extends Form
{
    /** Sample numbers used for the previews shown in the form. */
    public const SAMPLE_SUBMISSION_ID = 123;
    public const SAMPLE_SUBMISSION_FILE_ID = 456;
    public const SAMPLE_EXTENSION = '.pdf';

    public function __construct(
        public OjsbrFilenameRenamePlugin $plugin,
        public Context $context
    ) {
        parent::__construct($plugin->getTemplateResource('settings.tpl'));

        $this->addCheck(new FormValidatorInSet($this, 'numbersOnly', 'required', 'form.invalidFieldValue', ['0', '1']));
        $this->addCheck(new FormValidatorInSet($this, 'filenameLocale', 'required', 'form.invalidFieldValue', [
            OjsbrFilenameRenamePlugin::FILENAME_LOCALE_USER,
            OjsbrFilenameRenamePlugin::FILENAME_LOCALE_CONTEXT,
        ]));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Load the current settings of the journal.
     */
    public function initData()
    {
        $contextId = $this->context->getId();
        $this->setData('numbersOnly', $this->plugin->getSetting($contextId, OjsbrFilenameRenamePlugin::SETTING_NUMBERS_ONLY) ? '1' : '0');
        $this->setData('filenameLocale', $this->plugin->getSetting($contextId, OjsbrFilenameRenamePlugin::SETTING_FILENAME_LOCALE) === OjsbrFilenameRenamePlugin::FILENAME_LOCALE_CONTEXT
            ? OjsbrFilenameRenamePlugin::FILENAME_LOCALE_CONTEXT
            : OjsbrFilenameRenamePlugin::FILENAME_LOCALE_USER);
        parent::initData();
    }

    /**
     * Read the submitted settings.
     */
    public function readInputData()
    {
        $this->readUserVars(['numbersOnly', 'filenameLocale']);
        parent::readInputData();
    }

    /**
     * Render the form, with a sample name for each choice.
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $plugin = $this->plugin;
        $sample = fn (bool $numbersOnly, ?string $locale = null) => $plugin->buildFilename(
            self::SAMPLE_SUBMISSION_ID,
            self::SAMPLE_SUBMISSION_FILE_ID,
            self::SAMPLE_EXTENSION,
            $numbersOnly,
            $locale
        );

        $primaryLocale = $this->context->getPrimaryLocale();
        $languageName = Locale::getMetadata($primaryLocale)?->getDisplayName(null, true) ?? $primaryLocale;

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pluginName' => $plugin->getName(),
            'labelDescriptive' => __('plugins.generic.ojsbrFilenameRename.settings.format.descriptive', ['example' => $sample(false)]),
            'labelNumbersOnly' => __('plugins.generic.ojsbrFilenameRename.settings.format.numbersOnly', ['example' => $sample(true)]),
            'labelLocaleUser' => __('plugins.generic.ojsbrFilenameRename.settings.language.user'),
            'labelLocaleContext' => __('plugins.generic.ojsbrFilenameRename.settings.language.context', [
                'language' => $languageName,
                'example' => $sample(false, $primaryLocale),
            ]),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Save the settings of the journal.
     */
    public function execute(...$functionArgs)
    {
        $contextId = $this->context->getId();
        $this->plugin->updateSetting($contextId, OjsbrFilenameRenamePlugin::SETTING_NUMBERS_ONLY, $this->getData('numbersOnly') === '1', 'bool');
        $this->plugin->updateSetting($contextId, OjsbrFilenameRenamePlugin::SETTING_FILENAME_LOCALE, (string) $this->getData('filenameLocale'), 'string');
        return parent::execute(...$functionArgs);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\ojsbrFilenameRename\OjsbrFilenameRenameSettingsForm', '\OjsbrFilenameRenameSettingsForm');
}
