# Changelog

All notable changes to Chatvia are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Chatvia is now open source under the [MIT License](LICENSE).
- Community files: `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`,
  issue templates and a pull request template.
- `composer ci:check` runs every CI gate without changing files:
  `composer validate` and `composer audit`, `npm audit` for production
  dependencies, Pint, Rector, PHPStan, Prettier, ESLint, `vue-tsc`, the i18n
  check and the Pest test suite. `composer ci` fixes what it can first, then
  runs the same checks.
- One `docker-compose.yml` for local development: PostgreSQL 18 with the
  vector and BM25 search extensions, Redis and Mailpit, with health checks and
  an empty `testing` database for the test suite.

### Changed

- All Composer and npm dependencies were upgraded to their latest versions,
  including Pest 5, `laravel/mcp` 1.0, `spatie/laravel-permission` 8,
  `spatie/typescript-transformer` 3 and Intervention Image 4. Unused packages
  were removed.
- CI runs `composer ci:check` and uses a pinned PostgreSQL image. It needs no
  secrets, so it also runs for pull requests from forks.
- The README was rewritten for new contributors and self-hosters.
- The site footer now says that Chatvia is open source under the MIT License.

### Removed

- Editor and AI-assistant settings, generated IDE helper files and the unused
  generated `resources/js/ziggy.js` are no longer in the repository.
- A copyrighted test fixture that no test used any more.

### Security

- Fixed a number of security problems found in an internal audit. They cover
  sign-in and account checks, access control between workspaces, OAuth for
  connectors, signing of internal and outgoing webhooks, protection against
  server-side request forgery, rate limits on public endpoints, and secrets
  in logs. Regression tests were added for each fix. Self-hosters should
  update to this version.
