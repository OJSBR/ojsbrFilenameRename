# OJSBR Filename Rename — OJS plugin

[![OJS](https://img.shields.io/badge/OJS-3.4%20%7C%203.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-1.1.0.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

A generic plugin for **Open Journal Systems (OJS)** that renames the file **delivered to
the user at download time**, without patching the core. The file stored on disk and the
name shown in the editorial interface are **not** changed — only the `Content-Disposition`
of the HTTP response is adjusted at runtime.

It works through the native `File::download` hook and supports two naming modes,
configurable per journal:

- **Default** — `submissao-{submissionId}-arquivo-{submissionFileId}.{ext}`
- **Numbers only** — `{submissionId}-{submissionFileId}.{ext}`

> Developed and maintained by **[OJSBR](https://ojsbr.com.br)**.

## Compatibility / branches

| OJS version | Branch | Plugin release |
|-------------|--------|----------------|
| OJS 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.1.0.0 |
| OJS 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.1.0.0 |

Both branches share the same feature set (settings form + two naming modes); they differ
only in the OJS version they target. Also works on OPS 3.5.x.

## Installation

1. Download the release for your OJS version (or clone the matching branch).
2. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the
   folder into `plugins/generic/` so you get `plugins/generic/ojsbrFilenameRename/`.
3. Enable **OJSBR — Rename files on download** under the *Generic* plugins list.

## Configuration

Open the plugin **Settings** and choose the filename format:

- unchecked → default format `submissao-{id}-arquivo-{id}.{ext}`;
- **Numbers only** checked → `{id}-{id}.{ext}`.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OJS version
you are working against.

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE).

---

## 🇧🇷 Português

Plugin genérico para o **Open Journal Systems (OJS)** que renomeia o arquivo **entregue ao
usuário no momento do download**, sem alterar o core. O arquivo no disco e o nome exibido
na interface editorial **não** são alterados — apenas o `Content-Disposition` da resposta
HTTP é ajustado em tempo de execução.

Funciona pelo hook nativo `File::download` e oferece dois modos de nome, configuráveis por
revista:

- **Padrão** — `submissao-{submissionId}-arquivo-{submissionFileId}.{ext}`
- **Somente números** — `{submissionId}-{submissionFileId}.{ext}`

> Desenvolvido e mantido pela **[OJSBR](https://ojsbr.com.br)**.

### Compatibilidade / branches

| Versão do OJS | Branch | Release do plugin |
|---------------|--------|-------------------|
| OJS 3.5.x     | `stable-3_5_0` *(padrão)* | 1.1.0.0 |
| OJS 3.4.x     | `stable-3_4_0` | 1.1.0.0 |

As duas branches têm o mesmo conjunto de recursos (formulário de configuração + dois
modos de nome); diferem apenas na versão do OJS. Também funciona no OPS 3.5.x.

### Instalação

Instale em **Configurações → Website → Plugins → Enviar um novo plugin**, ou extraia a
pasta em `plugins/generic/` (ficando `plugins/generic/ojsbrFilenameRename/`). Depois ative
o **OJSBR — Rename files on download** na lista de plugins *Genéricos*.

### Configuração

Nas **Configurações** do plugin, escolha o formato do nome: desmarcado → formato padrão
`submissao-{id}-arquivo-{id}.{ext}`; **Somente números** marcado → `{id}-{id}.{ext}`.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE).
