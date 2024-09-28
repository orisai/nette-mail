# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/orisai/nette-mail/compare/1.010...v1.x)

### Changed

- `OverwriteRecipientMailer` - don't change recipient if the original one was not set. This change ensures that sending
  mail without recipient fails the same way on testing environment as it would on production environment.

## [1.0.1](https://github.com/orisai/nette-mail/compare/1.0.0...1.0.1) - 2024-06-21

### Added

- Allow PHP 8.3

### Changed

- Requires `orisai/clock:^1.2.0`

## [1.0.0](https://github.com/orisai/nette-mail/releases/tag/1.0.0) - 2023-10-29

Initial release
