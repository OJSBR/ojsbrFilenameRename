<?php

/**
 * @file plugins/generic/ojsbrFilenameRename/OjsbrFilenameRenamePlugin.php
 *
 * @class OjsbrFilenameRenamePlugin
 *
 * @ingroup plugins_generic_ojsbrFilenameRename
 *
 * @brief Plugin OJSBR - renomeia o arquivo entregue ao usuario no momento
 *        do download.
 *
 *        Modos (configuravel por revista nas Configuracoes do plugin):
 *
 *          - Modo padrao (numbersOnly = 0):
 *                submissao-{submissionId}-arquivo-{submissionFileId}.{ext}
 *
 *          - Somente numeros (numbersOnly = 1):
 *                {submissionId}-{submissionFileId}.{ext}
 *
 *        Funciona via hook nativo File::download do PKPFileService,
 *        sem patch no core. Nao altera o arquivo no disco nem o nome
 *        exibido na interface editorial. Compatibilidade: OJS 3.4.x.
 */

namespace APP\plugins\generic\ojsbrFilenameRename;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class OjsbrFilenameRenamePlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        // Nao registra durante instalacao/upgrade
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            // Hook chamado em PKPFileService::download(), no momento em
            // que o nome do arquivo entregue ao usuario e definido.
            //
            // Assinatura interna (3.4):
            //   HookRegistry::call('File::download', [$file, &$filename, $inline])
            //
            // Onde:
            //   $file      stdClass {file_id, path, mimetype}   (linha da tabela `files`)
            //   $filename  string                                (passado por referencia)
            //   $inline    bool
            Hook::add('File::download', [$this, 'renameOnDownload']);
        }

        return $success;
    }

    /**
     * Nome estavel do plugin no registry e nas URLs do gerenciador.
     * Com namespace, o getName() padrao retorna o FQCN em lower, que
     * nao bate com o que vai pela URL. Forcamos o nome curto da classe.
     *
     * @copydoc Plugin::getName()
     */
    public function getName()
    {
        return 'ojsbrfilenamerenameplugin';
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.ojsbrFilenameRename.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.ojsbrFilenameRename.description');
    }

    /**
     * Adiciona botao "Settings" na linha do plugin (configuracao por revista).
     *
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb)
    {
        $actions = parent::getActions($request, $verb);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        $linkAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->getName(),
                        'category' => 'generic',
                    ]
                ),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );
        array_unshift($actions, $linkAction);
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $context = $request->getContext();
        if (!$context) {
            return new JSONMessage(false);
        }

        $form = new OjsbrFilenameRenameSettingsForm($this, $context->getId());

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
     * Hook handler para File::download.
     *
     * Substitui o nome do arquivo entregue ao cliente HTTP. O formato
     * depende do setting `numbersOnly` configurado para a revista atual:
     *
     *   numbersOnly = 0 (padrao):
     *     submissao-{submissionId}-arquivo-{submissionFileId}.{ext}
     *
     *   numbersOnly = 1:
     *     {submissionId}-{submissionFileId}.{ext}
     *
     * Nada e alterado no disco nem em qualquer tabela; apenas o
     * Content-Disposition do response e ajustado em tempo de execucao.
     *
     * @param string $hookName
     * @param array  $args     [$file, &$filename, $inline]
     *
     * @return bool false para nao interromper a cadeia de hooks
     */
    public function renameOnDownload($hookName, $args)
    {
        // $file vem da tabela `files` (id, path, mimetype). Em alguns
        // caminhos do core o objeto e passado como stdClass; em outros
        // como instancia de PKP\file\File. Tratamos os dois casos.
        $file = $args[0] ?? null;
        if (!$file) {
            return false;
        }

        $fileId = null;
        if (is_object($file)) {
            if (isset($file->file_id)) {
                $fileId = (int) $file->file_id;
            } elseif (isset($file->id)) {
                $fileId = (int) $file->id;
            } elseif (method_exists($file, 'getId')) {
                $fileId = (int) $file->getId();
            }
        }
        if (!$fileId) {
            return false;
        }

        // Procura o submission_file ligado a este file_id.
        // Um mesmo file_id pode ter mais de um submission_file
        // (revisoes ao longo do fluxo editorial); pegamos o mais recente.
        $row = DB::table('submission_files')
            ->where('file_id', '=', $fileId)
            ->orderByDesc('submission_file_id')
            ->select(['submission_file_id', 'submission_id'])
            ->first();

        if (!$row) {
            return false;
        }

        // Extensao a partir do filename original recebido no hook.
        $ext = '';
        $original = $args[1] ?? '';
        if ($original !== '' && ($pos = strrpos($original, '.')) !== false) {
            $ext = substr($original, $pos);
        }
        // Fallback: tenta pegar a extensao do caminho fisico do arquivo.
        if ($ext === '' && is_object($file) && !empty($file->path)) {
            $pinfo = pathinfo($file->path);
            if (!empty($pinfo['extension'])) {
                $ext = '.' . $pinfo['extension'];
            }
        }

        // Decide o formato pelo setting da revista atual.
        $args[1] = sprintf(
            $this->getFilenameTemplate(),
            (int) $row->submission_id,
            (int) $row->submission_file_id,
            $ext
        );

        return false;
    }

    /**
     * Le o setting `numbersOnly` do contexto atual e retorna o
     * template de sprintf a ser usado.
     *
     * @return string sprintf template com 3 placeholders (%d, %d, %s)
     */
    protected function getFilenameTemplate()
    {
        $numbersOnly = false;

        $request = Application::get()->getRequest();
        $context = $request ? $request->getContext() : null;
        if ($context) {
            $numbersOnly = (bool) $this->getSetting($context->getId(), 'numbersOnly');
        }

        return $numbersOnly
            ? '%d-%d%s'
            : 'submissao-%d-arquivo-%d%s';
    }
}

// Compatibilidade legacy do OJS 3.4: o registry procura a classe pelo
// nome curto sem namespace. Quando PKP_STRICT_MODE nao esta ativo,
// criamos um alias para que `OjsbrFilenameRenamePlugin` (sem namespace)
// resolva para a classe namespaced.
if (!PKP_STRICT_MODE) {
    class_alias(
        '\APP\plugins\generic\ojsbrFilenameRename\OjsbrFilenameRenamePlugin',
        '\OjsbrFilenameRenamePlugin'
    );
}
