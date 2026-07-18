# Sukarix Framework

Open-source PHP 8.4+ framework built on the Fat-Free Framework (F3).
MIT licensed. Contributions welcome.

## Quick start

```bash
composer install
php tools/statera.php        # run tests
```

## Tech stack

- PHP 8.4+, strict types
- Fat-Free Framework 3.9 (F3) — routing, hive, Registry, `\Test`
- F3 Cortex (ORM), F3 Access (ACL), F3 Multilang (i18n)
- Statera (`sukarix/statera`) — testing kit, not PHPUnit
- Monolog, Tracy, Carbon, Respect\Validation

## Architecture

- **Hive**: `\Base::instance()->get/set` — central config store, loaded from `.ini`
- **Injector** (`Sukarix\Core\Injector`): DI container. Resolve services via `Injector::instance()->get('alias')`
- **Tailored**: Abstract Prefab singleton base. Subclasses call `Processor::instance()->initialize($this)` in constructor
- **Actions**: `WebAction` (renders templates) and `Action` (CLI). Both receive `$f3, $params` in `execute()`
- **Models**: Extend `Sukarix\Models\Model` (→ `DB\Cortex`)
- **Behaviours**: Traits — `HasF3`, `HasSession`, `HasI18n`, `HasAssets`, `HasMessages`, `HasEvents`, `HasCache`, `HasAccess`

## Testing

Tests use **Statera**, never PHPUnit.

```php
$test = $this->newTest();
$test->expect($condition, 'message');
return $test->results();
```

Exceptions: try/catch with `expect($threw, '...')`. Run: `php tools/statera.php`.

## Conventions

- `declare(strict_types=1)` in every file
- Typed properties and return types everywhere
- Resolve services through the Injector, not `new`
- Use F3 hive for configuration, not env vars directly
- Log via `LogWriter` trait (`$this->logger->...`)
- Session via `\Registry::get('session')`, never `$_SESSION`

## AI usage

This project is developed with AI assistance (Devin, GitHub Copilot, and others).
AI-generated contributions are welcome and should follow the same conventions as human contributions.

## Commits

One logical change per commit.

```
<description>

Co-Authored-By: Devin
```
