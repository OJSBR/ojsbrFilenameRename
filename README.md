# OJSBR Filename Rename — OJS plugin (OJS 3.4 branch)

[![OJS](https://img.shields.io/badge/OJS-3.4-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-1.1.0.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

> **This is the `stable-3_4_0` branch (OJS 3.4).** For OJS 3.5 use the
> [`stable-3_5_0`](../../tree/stable-3_5_0) branch.

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
| OJS 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) *(this branch)* | 1.1.0.0 |

## Installation

Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the folder
into `plugins/generic/` (giving `plugins/generic/ojsbrFilenameRename/`). Then enable
**OJSBR — Rename files on download** under the *Generic* plugins list.

## Configuration

Open the plugin **Settings** and choose the filename format: unchecked → default format
`submissao-{id}-arquivo-{id}.{ext}`; **Numbers only** checked → `{id}-{id}.{ext}`.

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE).

---

## 🇧🇷 Português

> **Esta é a branch `stable-3_4_0` (OJS 3.4).** Para OJS 3.5 use a branch
> [`stable-3_5_0`](../../tree/stable-3_5_0).

Plugin genérico para o **Open Journal Systems (OJS)** que renomeia o arquivo **entregue ao
usuário no momento do download**, sem alterar o core. O arquivo no disco e o nome exibido
na interface editorial **não** são alterados. Funciona pelo hook nativo `File::download`,
com dois modos configuráveis por revista: **Padrão**
(`submissao-{submissionId}-arquivo-{submissionFileId}.{ext}`) e **Somente números**
(`{submissionId}-{submissionFileId}.{ext}`).

> Desenvolvido e mantido pela **[OJSBR](https://ojsbr.com.br)**.

### Instalação

Instale em **Configurações → Website → Plugins → Enviar um novo plugin**, ou extraia a
pasta em `plugins/generic/` (ficando `plugins/generic/ojsbrFilenameRename/`). Depois ative
o **OJSBR — Rename files on download** na lista de plugins *Genéricos*.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE).
