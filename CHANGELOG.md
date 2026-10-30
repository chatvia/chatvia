# Changelog

All notable changes to ChatVia.ai are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Files: a ZIP archive can be uploaded on the **Files** page. It is unpacked on
  the server, folders and archives inside it included, and every file of a type
  the install accepts becomes its own source file, named with its folder
  (`policies/refunds.pdf`). Other formats, locked PDFs, files the workspace
  already holds and files over 50 MB are left out and named in a message after
  the upload. One ZIP holds up to 500 files, nested up to four levels deep. The
  archive itself is not kept.

## [1.1.0] - 2026-10-30

### Added

- Evals: a **Generate with AI** button in the "From knowledge" picker. It asks
  the quick-task model for five questions drawn from a random sample of the
  agent's knowledge, each tied to the passage that answers it, so an agent whose
  sources were trained without mined questions has something to pick. Clicking
  again samples different passages.

### Changed

- Installer: the news question is now a Yes/No on the admin e-mail address
  instead of a separate e-mail field. `--news` / `--no-news` (`CHATVIA_NEWS`)
  replace `--news-email` / `--no-news-email`.
- Installer: the install-count question starts on Yes, and the closing message
  is shown in green.
- `./manage stop` and `./manage uninstall` can take over a hung command, such as
  a restart or upgrade stuck in Docker Compose. They name the running command,
  ask to stop it (`uninstall --yes` does so without asking), end it together
  with what it started, and carry on. Other commands, and a stop from cron,
  still wait as before.

### Fixed

- Installer: the "your own proxy handles HTTPS" choice now writes `APP_BIND_IP`
  and `TRUSTED_PROXIES` the same way the built-in HTTPS modes do, and
  `./manage https off` keeps them when a domain is set. Before, both were left
  to the person with a next-steps line that could not be followed.

## [1.0.0] - 2026-10-29

The first public version of ChatVia.ai.

[Unreleased]: https://github.com/chatvia/chatvia/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/chatvia/chatvia/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/chatvia/chatvia/releases/tag/v1.0.0
