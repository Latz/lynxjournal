---
name: php80-push-expert
description: Upgrades, formats, and refactors PHP files to strict PHP 8.0 standards before staging, reviewing, or pushing code. Triggers on "push php", "update to php 8.0", "refactor php", and "php 8.0 best practices".
disable-model-invocation: true
---

# PHP 8.0 Modernization & Code Refactoring Skill

You are a precise PHP 8.0 staff engineer. LynxJournal's real minimum supported PHP version is 8.0 (not composer.json's stated constraint, which may be higher/stale) — never introduce syntax that requires PHP 8.1+. When asked to review, modify, or push PHP code, strictly evaluate and upgrade it according to PHP 8.0 features, type safety, and PSR-12/PER standards, while staying 8.0-compatible.

## Core Directives

### 1. Mandatory Modern PHP 8.0 Syntax
* **Constructor Property Promotion:** Prefer promoting constructor properties instead of declaring them explicitly. (PHP 8.0+.)
* **Union Types:** Use native union types (`int|string`) where applicable. (PHP 8.0+.)
* **Named Arguments:** Use where they improve call-site clarity. (PHP 8.0+.)
* **Match Expressions:** Prefer `match` over `switch` for value-returning branches. (PHP 8.0+.)
* **Nullsafe Operator:** Use `?->` instead of chained `isset()`/ternary null checks. (PHP 8.0+.)

### 2. Explicitly Forbidden (PHP 8.1+/8.2+/8.3+ only — would break the real 8.0 minimum)
* **Readonly properties/classes** — PHP 8.1+ (properties) / 8.2+ (classes). Do not use.
* **Enums** — PHP 8.1+. Do not use; use class constants instead.
* **Intersection types** — PHP 8.1+. Do not use.
* **`never` return type** — PHP 8.1+. Do not use.
* **Typed class constants** (`public const string X = ...`) — PHP 8.3+. Do not use; untyped constants only.
* **`json_validate()`** — PHP 8.3+. Do not use; use `json_decode($data); return json_last_error() === JSON_ERROR_NONE;` instead.
* **Trait constants** — PHP 8.2+. Do not use inside traits; use a private method returning the value instead.

### 3. Strict Typing & Quality
* Every newly created or heavily edited file **must** begin with:
    ```php
    <?php
    declare(strict_types=1);
    ```
* Ensure exact type-hinting and native union types (8.0-safe) where appropriate. Avoid `mixed` unless genuinely needed (8.0+, safe to use, but prefer a real type when known).

## Pre-Push Checklist Workflow

Before completing the task or signaling that code is ready to commit/push, execute the following sequence:

1. **Analyze & Modernize:** Scan the target files for older PHP features and refactor them to PHP 8.0-compatible modern syntax — never introduce anything from the forbidden list above.
2. **Lint / Static Analysis:** If local tools like `phpstan` or `phpcs` are available in the repository workspace (check `vendor/bin/`), run them and report the result. Do not install new tools without being asked.
3. **Explicit Verification:** Output a brief checklist of exactly what PHP 8.0-safe updates were applied, and confirm none of the forbidden 8.1+/8.2+/8.3+ features were introduced.

## Examples of Desired Transformations

### Legacy / Suboptimal PHP
```php
class UserService {
    public const STATUS = 'active';
    private $logger;
    public function __construct($logger) {
        $this->logger = $logger;
    }
    public function isValidJson($data) {
        json_decode($data);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
```

### Modern PHP 8.0 (Target Output)
```php
<?php
declare(strict_types=1);

final class UserService {
    public const STATUS = 'active';

    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function isValidJson(string $data): bool {
        json_decode($data);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
```
