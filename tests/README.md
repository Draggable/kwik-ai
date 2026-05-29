# KWIK AI Tests

## Overview

This directory contains the test suite for the KWIK AI WordPress plugin.

## Requirements

- PHP 7.4+ (for compatibility with older WordPress versions)
- Composer (for dependency management)
- PHPUnit 11+ (installed via Composer)
- PHP_CodeSniffer (installed via Composer)

## Installation

```bash
# Install dependencies
composer install
```

## Running Tests

### Run All Tests

```bash
composer test
```

### Run Tests with Coverage Report

```bash
composer test:coverage
```

This will generate an HTML coverage report in `tests/coverage/`.

### Run Only Unit Tests

```bash
composer test:unit
```

### Run Only Integration Tests

```bash
composer test:integration
```

### Run a Specific Test

```bash
vendor/bin/phpunit tests/unit/TestSettings.php
```

### Run Tests with Verbose Output

```bash
vendor/bin/phpunit --verbose
```

## Code Quality

### Run PHP_CodeSniffer

```bash
composer lint
```

### Fix Code Style Issues Automatically

```bash
composer lint:fix
```

### Check PHP Compatibility

```bash
composer lint:php
```

### Check WordPress Coding Standards

```bash
composer lint:wp
```

## Test Coverage Goals

- Overall coverage: 80%
- Per-file coverage: 70%

## Adding New Tests

### Unit Tests

1. Create a new test file in `tests/unit/`
2. Name it `Test{ClassName}.php`
3. Extend `PHPUnit\Framework\TestCase`
4. Write test methods starting with `test`
5. Use PHPUnit assertions to verify behavior

### Integration Tests

1. Create a new test file in `tests/integration/`
2. Name it `Test{FeatureName}.php`
3. Extend `PHPUnit\Framework\TestCase`
4. Test actual plugin functionality
5. Use mocks for WordPress functions as needed

## Test Structure

```
tests/
├── bootstrap.php           # Test environment setup
├── unit/                   # Unit tests
│   ├── TestSettings.php
│   ├── TestApiFunctions.php
│   └── TestTagGeneration.php
└── integration/            # Integration tests
    └── TestPluginIntegration.php
```

## Mocking WordPress Functions

The `tests/bootstrap.php` file provides mock implementations of common WordPress functions.
For more complex mocking, use PHPUnit's built-in mocking capabilities or the `yoast/phpunit-polyfills` package.

## Continuous Integration

These tests are designed to run in CI environments. The following commands can be used in CI:

```bash
# Install dependencies
composer install --no-dev

# Run tests
composer test

# Check code quality
composer lint
```

## Troubleshooting

### "Function not found" Errors

Make sure `tests/bootstrap.php` is being loaded. Check that the `bootstrap` attribute in `phpunit.xml` is correct.

### "Call to undefined function" Errors

The bootstrap file provides mock implementations of WordPress functions. If you're seeing these errors, ensure your test is using the mocked versions.

### "WordPress not installed" Errors

Some tests may require a WordPress installation. The bootstrap file provides minimal mocks for basic functionality. For full WordPress testing, consider using the WordPress PHPUnit testing framework.
