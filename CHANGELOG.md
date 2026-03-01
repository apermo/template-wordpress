# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - Unreleased

### Added

- Initial project setup
- Optional WordPress.org SVN deploy workflow
- WordPress integration test infrastructure with multisite matrix
- `wp-tests-config.php.dist` for CI test suite configuration
- WP beta/RC nightly compatibility workflow
- Playwright E2E test infrastructure with auth setup and example spec
- E2E caller workflow (`e2e.yml`)
- `WP_DB_IMPORT` support in `.ddev/.env` for database dump import

### Changed

- Integration test bootstrap auto-detects `vendor/wp-phpunit/wp-phpunit`
