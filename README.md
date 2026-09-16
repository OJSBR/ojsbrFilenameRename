# Rename Files on Download — OJS and OMP plugin

[![OJS](https://img.shields.io/badge/OJS-3.4%20%7C%203.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![OMP](https://img.shields.io/badge/OMP-3.5-brightgreen)](https://pkp.sfu.ca/omp/)
[![Version](https://img.shields.io/badge/version-1.2.1.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OJS / OMP 3.5](https://github.com/OJSBR/ojsbrFilenameRename/releases/download/1.2.1.0/ojsbrFilenameRename-1.2.1.0.tar.gz) · [OJS 3.4](https://github.com/OJSBR/ojsbrFilenameRename/releases/download/1.2.0.1-ojs3.4/ojsbrFilenameRename-1.2.0.1-ojs3.4.tar.gz) — or browse all [Releases](../../releases).

A generic plugin for **Open Journal Systems (OJS)** and **Open Monograph Press (OMP)** that
delivers submission files under a **neutral, standardized name** when they are downloaded —
`submission-123-file-456.pdf` — written in the language of the person downloading or in the
primary language of the journal or press, or as numbers only (`123-456.pdf`). The file stored on the server and the name shown in the editorial
interface are **not** changed.

> **Developed and maintained by [OJSBR](https://ojsbr.com).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| Application | Branch | Plugin release |
|-------------|--------|----------------|
| OJS 3.5.x and OMP 3.5.x | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.2.1.0 |
| OJS 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.2.0.1 |

> Since 1.2.1.0 the same package serves OJS and OMP. The former `ojsbrFilenameRenameOmp`
> repository is archived; its releases stay available there.

Both branches ship the same features from **1.2.0.0**. The locale folders follow the codes of
each OJS line (OJS 3.4: `fr_FR`, `pt_PT`, `nb`, `sr@latin`, `zh_CN`; OJS 3.5: `fr`, `pt`, `nb_NO`,
`sr_Latn`, `zh_Hans`).

## The problem

Files arrive in a journal under whatever name the author gave them: the author's name, the
title, "final version (3)". Those names travel to reviewers, copyeditors and readers, can
break double-blind review and say nothing about which submission the file belongs to. Renaming
every file by hand is not an option.

## What it does

- Every **submission file** downloaded — from the editorial workflow, by a reviewer, or as a
  published galley — is delivered as `submission-{submissionId}-file-{submissionFileId}.{ext}`.
- **The name is translated.** It follows the interface language of the person downloading, or,
  if the journal prefers, always its primary language. Examples of the 38 languages shipped:

  | Language | File name |
  |----------|-----------|
  | English | `submission-123-file-456.pdf` |
  | Português (Brasil) | `submissao-123-arquivo-456.pdf` |
  | Português (Portugal) | `submissao-123-ficheiro-456.pdf` |
  | Español | `envio-123-fichero-456.pdf` |
  | Français | `soumission-123-fichier-456.pdf` |
  | Deutsch | `einreichung-123-datei-456.pdf` |
  | 日本語 | `投稿-123-ファイル-456.pdf` |

- **Numbers only**, if preferred: `123-456.pdf`, the same in every language.
- The number is the one of the file that was clicked, even when a review-stage copy shares the
  stored file with the original.
- Files that are not submission files (library files, issue galleys) keep their name.
- Latin-script languages use no diacritics, so names survive any file system, archive or e-mail.

## Installation

1. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the folder into
   `plugins/generic/` so that you get `plugins/generic/ojsbrFilenameRename/`.
   Do not rename the folder: OJS derives the plugin's class namespace from the directory name.
2. Enable **Rename Files on Download** in the *Generic* plugins list.

Upgrading from 1.1.x keeps the journal's settings.

## Configuration

Open the plugin's **Settings**. Both choices show a live example in the current language.

- **File name format** — *Descriptive name* (default) or *Numbers only*.
- **Language of the descriptive name** — *Interface language of the person downloading*
  (default) or *Primary language of the journal*. A language the plugin has no translation for
  falls back to English.

## How it works (technical)

- Only hooks, no core patch and no template override: `File::download`, called by
  `PKPFileService::download()` right before the headers are sent, receives the stored file and
  the offered name by reference; the plugin replaces the name.
- The submission file is found by the stored `file_id` (indexed). When several submission files
  share it, the one named by the request wins — `?submissionFileId=` in the workflow, the last
  path argument of `article/download/{submissionId}/{galleyId}/{submissionFileId}` — and the
  newest otherwise.
- The name is the locale key `plugins.generic.ojsbrFilenameRename.filename.descriptive`, with
  the placeholders `{$submissionId}` and `{$submissionFileId}`, so translators control the words
  and their order. The result is sanitized (no path separators, reserved or control characters,
  whitespace turned into hyphens, 150 characters at most). A translation that is missing or has
  lost a placeholder falls back to English, then to numbers only — never `##key##`.
- The extension is taken from the stored path (generated by OJS), then from the offered name;
  `.tar.gz` is kept whole.
- Settings `numbersOnly` (bool) and `filenameLocale` (`user`|`context`) are stored per journal;
  the registry name `ojsbrfilenamerenameplugin` is unchanged from 1.1.x.

## Tests

- **PHPUnit** (`tests/*Test.php`, on `PKP\tests\PKPTestCase`): translations (38 locales, identical
  keys, placeholders, fuzzy markers, file-system-safe names), file name building in every
  language, fallbacks, sanitization, extension, the submission file chosen for a shared stored
  file, the component router of the workflow, the settings form and its validation, the site level
  without settings, and the plugin classes against the installed PKP. From the OJS root:

  ```bash
  lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage "$PWD/plugins/generic/ojsbrFilenameRename/tests"
  ```

- **Cypress** (`cypress/tests/functional/OjsbrFilenameRename.cy.js`, run by
  [pkp-github-actions](https://github.com/pkp/pkp-github-actions) on every push): enables the
  plugin, finds a published galley through the REST API, checks the name it is downloaded under in
  every language of the journal (it fails with the hook off), and the effect of each setting,
  putting the defaults back.
- Verified on OJS 3.5.0.3, OMP 3.5.0.3 and OJS 3.4.0.10: workflow, galley and publication format downloads in pt_BR, en and es, both
  formats, both language settings, disabling and re-enabling.

Tests are kept in the repository and are not part of the release package.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com) — original plugin.
- Distributed under the **GNU GPL v3**.

## AI use

Generative AI (Claude, by Anthropic) was used to write and run tests, improve the code and bring
it in line with PKP standards. Every change is reviewed and tested by OJSBR, which is responsible
for the published releases.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OJS version you are
working against, and keep every locale with exactly the keys of `locale/en/locale.po`.

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

Plugin genérico para o **Open Journal Systems (OJS)** e o **Open Monograph Press (OMP)** que
entrega os arquivos da submissão com um **nome neutro e padronizado** no download —
`submissao-123-arquivo-456.pdf` — no idioma de quem baixa ou no idioma principal da revista ou
da editora, ou somente com números (`123-456.pdf`). O arquivo
armazenado no servidor e o nome exibido na interface editorial **não** são alterados.

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com).** Veja a seção
> [Créditos e autoria](#créditos-e-autoria) abaixo.

### Compatibilidade e branches

| Aplicação | Branch | Release do plugin |
|-----------|--------|-------------------|
| OJS 3.5.x e OMP 3.5.x | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.2.1.0 |
| OJS 3.4.x     | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.2.0.1 |

> A partir da 1.2.1.0 o mesmo pacote serve OJS e OMP. O repositório `ojsbrFilenameRenameOmp`
> está arquivado; as releases dele continuam disponíveis lá.

As duas branches têm os mesmos recursos a partir da **1.2.0.0**. As pastas de idioma seguem os
códigos de cada linha do OJS (OJS 3.4: `fr_FR`, `pt_PT`, `nb`, `sr@latin`, `zh_CN`; OJS 3.5: `fr`,
`pt`, `nb_NO`, `sr_Latn`, `zh_Hans`).

### O problema

Os arquivos chegam à revista com o nome que o autor deu: o nome dele, o título, "versão final
(3)". Esse nome vai para avaliadores, revisores e leitores, pode quebrar a avaliação duplo-cega e
não diz a qual submissão o arquivo pertence.

### O que faz

- Todo **arquivo de submissão** baixado — no fluxo editorial, pelo avaliador ou como composição
  publicada — é entregue como `submissao-{ID da submissão}-arquivo-{ID do arquivo}.{ext}`.
- **O nome é traduzido**: segue o idioma da interface de quem baixa ou, se a revista preferir,
  sempre o idioma principal dela. São 38 idiomas (veja exemplos na tabela da seção em inglês).
- **Somente números**, se preferir: `123-456.pdf`, igual em todos os idiomas.
- O número é o do arquivo clicado, mesmo quando a cópia da etapa de avaliação compartilha o
  arquivo armazenado com o original.
- Arquivos que não são de submissão (biblioteca, composições de edição) mantêm o nome.
- Idiomas de alfabeto latino não usam acentos, para o nome sobreviver a qualquer sistema,
  compactador ou e-mail.

### Instalação

1. Instale em **Configurações → Website → Plugins → Enviar um novo plugin**, ou extraia a pasta
   em `plugins/generic/` (ficando `plugins/generic/ojsbrFilenameRename/`). Não renomeie a pasta:
   o OJS deriva o namespace da classe do nome do diretório.
2. Ative **Renomear Arquivos no Download** na lista de plugins *Genéricos*.

A atualização a partir da 1.1.x mantém as configurações da revista.

### Configuração

Abra as **Configurações** do plugin. As duas escolhas mostram um exemplo no idioma atual.

- **Formato do nome do arquivo** — *Nome descritivo* (padrão) ou *Somente números*.
- **Idioma do nome descritivo** — *Idioma da interface de quem baixa* (padrão) ou *Idioma
  principal da revista*. Um idioma sem tradução no plugin usa o inglês.

### Testes

PHPUnit em `tests/` (sobre `PKP\tests\PKPTestCase`) e Cypress em `cypress/tests/functional/`
(rodado pelo [pkp-github-actions](https://github.com/pkp/pkp-github-actions) a cada push), com os
comandos da seção em inglês. O Cypress acha uma composição publicada pela API REST, confere o nome
de download em cada idioma da revista e o efeito de cada configuração, devolvendo o padrão.
Verificado no OJS 3.5.0.3, no OMP 3.5.0.3 e no OJS 3.4.0.10: downloads do fluxo editorial, de composições e de formatos de publicação em pt_BR, en e
es, os dois formatos, as duas opções de idioma, desativar e reativar.

Os testes ficam no repositório e não fazem parte do pacote da release.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com) — plugin autoral.
- Distribuído sob a **GNU GPL v3**.

### Uso de IA

Foi usada IA generativa (Claude, da Anthropic) para escrever e rodar testes, melhorar o código e
alinhá-lo aos padrões da PKP. Toda mudança é revisada e testada pela OJSBR, que responde pelas
releases publicadas.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
