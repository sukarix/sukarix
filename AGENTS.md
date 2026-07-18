# Sukarix Framework — Agent Guidelines

## Project

Sukarix is a PHP 8.4+ framework built on top of the Fat-Free Framework (F3).
It provides dependency injection, Cortex-based models, CLI actions, session
management, and the Statera testing kit.

## Tech stack

- **PHP 8.4+**, strict types everywhere (`declare(strict_types=1)`)
- **Fat-Free Framework 3.9** (F3) — routing, hive, Registry, `\Test` class
- **F3 Cortex** — ORM; **F3 Access** — ACL; **F3 Multilang** — i18n
- **Monolog**, **Tracy**, **Carbon**, **Respect\Validation**
- **Statera** (`sukarix/statera`) — the framework's own testing kit (NOT PHPUnit)

## Testing

- Tests use **Statera**, never PHPUnit.
- Run tests: `php tools/statera.php`
- Test scenarios extend `Sukarix\TestScenario`; assertions use `$test->expect($cond, $text)`.
- For exception testing, use try/catch with `expect()` — Statera has no `expectException`.

## Code style

- Follow existing PSR-12 + project conventions.
- Use F3 hive (`\Base::instance()->get/set`) for configuration, not env vars directly.
- DI via `Sukarix\Core\Injector` — resolve services through the injector, not `new` in controllers.
- Prefab singletons via `Tailored::instance()`.

## Commits

- One logical change per commit (unitary commits).
- Author: Ghazi Triki <ghazi.triki@riadvice.com>
- Co-author trailer:
  ```
  Co-Authored-By: Devin <158243242+devin-ai-integration[bot]@users.noreply.github.com>
  ```
- Format:
  ```
  <short description>

  Generated with [Devin](https://devin.ai)

  Co-Authored-By: Devin <158243242+devin-ai-integration[bot]@users.noreply.github.com>
  ```
