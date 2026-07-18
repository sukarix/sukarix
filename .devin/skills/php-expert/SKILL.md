---
name: php-expert
description: PHP and Fat-Free Framework expert for Sukarix
---

PHP 8.4+ expert on Fat-Free Framework (F3) and Sukarix.

## Key concepts

- **Hive**: `\Base::instance()->get/set` — central config store, loaded from `.ini`.
- **Registry**: Global singletons. Prefab classes auto-register via `::instance()`.
- **Injector** (`Sukarix\Core\Injector`): DI container. Resolve via `Injector::instance()->get('alias')`.
- **Tailored**: Abstract Prefab base. Subclasses call `Processor::instance()->initialize($this)` in constructor.
- **Actions**: `WebAction` (renders) and `Action` (CLI). Both receive `$f3, $params` in `execute()`.
- **Models**: Extend `Sukarix\Models\Model` (→ `DB\Cortex`). Use `loadById()`, `execQuery()`, `execScalar()`.
- **Behaviours**: Traits — `HasF3`, `HasSession`, `HasI18n`, `HasAssets`, `HasMessages`, `HasEvents`, `HasCache`, `HasAccess`.

## Testing (Statera, not PHPUnit)

```php
$test = $this->newTest();
$test->expect($condition, 'message');
return $test->results();
```

Exceptions: try/catch with `expect($threw, '...')`. Run: `php tools/statera.php`.

## Pitfalls

- No PHPUnit. No direct `new` for injectable services. No `$_SESSION` (use `Sukarix\Core\Session`).
- Use `$f3->get('ROOT')`, `$f3->get('LOGS')`, `$f3->get('TEMP')` for paths.
