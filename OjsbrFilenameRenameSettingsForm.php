<?php

/**
 * @file plugins/generic/ojsbrFilenameRename/OjsbrFilenameRenameSettingsForm.php
 *
 * @class OjsbrFilenameRenameSettingsForm
 *
 * @ingroup plugins_generic_ojsbrFilenameRename
 *
 * @brief Formulario de configuracao do plugin por revista.
 */

namespace APP\plugins\generic\ojsbrFilenameRename;

use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;

class OjsbrFilenameRenameSettingsForm extends Form
{
    /** @var int ID do contexto (revista) */
    public $contextId;

    /** @var OjsbrFilenameRenamePlugin */
    public $plugin;

    public function __construct($plugin, $contextId)
    {
        $this->contextId = $contextId;
        $this->plugin = $plugin;
        parent::__construct($plugin->getTemplateResource('settings.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $this->setData(
            'numbersOnly',
            (bool) $this->plugin->getSetting($this->contextId, 'numbersOnly')
        );
        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['numbersOnly']);
        parent::readInputData();
    }

    /**
     * @copydoc Form::fetch()
     *
     * Disponibiliza pluginName no template para a action de salvar
     * conseguir resolver o plugin pelo registry.
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $this->plugin->updateSetting(
            $this->contextId,
            'numbersOnly',
            (bool) $this->getData('numbersOnly'),
            'bool'
        );
        return parent::execute(...$functionArgs);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias(
        '\APP\plugins\generic\ojsbrFilenameRename\OjsbrFilenameRenameSettingsForm',
        '\OjsbrFilenameRenameSettingsForm'
    );
}
