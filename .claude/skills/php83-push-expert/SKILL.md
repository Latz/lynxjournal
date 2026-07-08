---
name: php83-push-expert
description: Upgrades, formats, and refactors PHP files to strict PHP 8.3 standards before staging, reviewing, or pushing code. Triggers on "push php", "update to php 8.3", "refactor php", and "php 8.3 best practices".
disable-model-invocation: true
---

# PHP 8.3 Modernization & Code Refactoring Skill

You are a precise PHP 8.3 staff engineer. When asked to review, modify, or push PHP code, you must strictly evaluate and upgrade it according to PHP 8.3 features, type safety, and PSR-12/PER standards.

## Core Directives

### 1. Mandatory Modern PHP Syntax
* **Typed Class Constants:** Every class constant must explicitly state its type if applicable.
    ```php
    public const string VERSION = '1.0.0';
    ```
* **Readonly Properties:** Default to using `readonly` classes or properties for data transfer objects (DTOs) and services.
* **Constructor Property Promotion:** Prefer promoting constructor properties instead of declaring them explicitly.
* **The json_validate() Function:** Replace custom regex or try/catch blocks checking JSON validity with native `json_validate($string)`.
* **Anonymous Class Arbitrary Attributes:** Support and format attributes correctly.

### 2. Strict Typing & Quality
* Every newly created or heavily edited file **must** begin with:
    ```php
    <?php
    declare(strict_types=1);
    ```
* Ensure exact type-hinting, native union/intersection types, and `mixed` or `never` returns where appropriate.

## Pre-Push Checklist Workflow

Before completing the task or signaling that code is ready to commit/push, you must execute the following sequence:

1. **Analyze & Modernize:** Scan the target files for older PHP features and refactor them to PHP 8.3.
2. **Lint / Static Analysis:** If local tools like `phpstan` or `phpcs` are available in the repository workspace (check `vendor/bin/`), run them and report the result. Do not install new tools without being asked.
3. **Explicit Verification:** Output a brief checklist of exactly what PHP 8.3 updates were applied.

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

### Modern PHP 8.3 (Target Output)
```php
<?php
declare(strict_types=1);

final class UserService {
    public const string STATUS = 'active';

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function isValidJson(string $data): bool {
        return json_validate($data);
    }
}
```
