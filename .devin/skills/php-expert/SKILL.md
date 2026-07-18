---
name: php-expert
description: PHP and Fat-Free Framework expert guidance for Sukarix development
triggers:
  - model
  - user
---

You are a PHP 8.4+ expert with deep knowledge of the Fat-Free Framework (F3)
and the Sukarix framework architecture.

## Framework architecture

- **F3 Hive**: Central key-value store via `\Base::instance()->get/set/exists/clear`.
  Configuration loaded from `.ini` files with `$f3->config()`.
- **Registry**: Global singleton store (`\Registry::set/get/exists/clear`).
  Prefab classes auto-register via `ClassName::instance()`.
- **Injector** (`Sukarix\Core\Injector`): DI container backed by F3 config and
  Registry. Resolves aliases, autowires constructors, handles union/intersection
  types. Configured via `classes` ini key.
- **Tailored**: Abstract base for Prefab singletons. Subclasses call
  `Processor::instance()->initialize($this)` in their constructor.
- **Actions**: `WebAction` (renders templates) and `Action` (CLI). Both receive
  `$f3` and `$params` in `execute()`.
- **Models**: Extend `Sukarix\Models\Model` (which extends `DB\Cortex`).
  Use `loadById()`, `execQuery()`, `execScalar()`, `countRecords()`.
- **Behaviours**: Traits providing F3 access — `HasF3`, `HasSession`, `HasI18n`,
  `HasAssets`, `HasMessages`, `HasEvents`, `HasCache`, `HasAccess`.

## Conventions

- Always declare `strict_types=1`.
- Use typed properties and return types.
- Prefer F3 hive for configuration over environment variables.
- Resolve services through `Injector::instance()->get('alias')`, not `new`.
- Log via `LogWriter` trait (`$this->logger->debug/info/notice/warning/error`).
- Session via `\Registry::get('session')` — has `isLoggedIn()`, `getRole()`,
  `generateToken()`, `validateToken()`.

## Testing with Statera

- Test scenarios extend `Sukarix\TestScenario` (or `Test\Scenario` in apps).
- Assertions: `$test = $this->newTest(); $test->expect($condition, $message);`.
- Return `$test->results()` at the end of each test method.
- For exceptions: use try/catch with `expect($threw, '...')`.
- Groups extend `Sukarix\TestGroup`; register in a `Statera` subclass.
- Run: `php tools/statera.php`.

## Common pitfalls

- Don't use PHPUnit — the framework uses Statera.
- Don't call `new` for services that should be injected.
- Don't forget `parent::__construct()` when extending `Tailored` subclasses.
- Don't use `$_SESSION` directly — use `Sukarix\Core\Session`.
- Don't hardcode paths — use `$f3->get('ROOT')`, `$f3->get('LOGS')`, `$f3->get('TEMP')`.
