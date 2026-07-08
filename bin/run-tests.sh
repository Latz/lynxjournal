#!/usr/bin/env bash

echo "=========================================="
echo "LynxJournal Test Suite"
echo "=========================================="
echo ""

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

# composer.phar emits PHP deprecation notices to stdout on PHP 8.5+; strip them.
composer_run() {
  composer run "$@" 2>&1 | grep -v "^Deprecation Notice:"
  return "${PIPESTATUS[0]}"
}

EXIT_CODES=()
ENV_STARTED=0

# Cleanup function to stop environment on exit
cleanup() {
  if [ $ENV_STARTED -eq 1 ]; then
    echo ""
    echo "Stopping WordPress environment..."
    npm run env:stop > /dev/null 2>&1
  fi
}

trap cleanup EXIT

# Run PHP Unit Tests
echo "Running PHP Unit Tests (Pest)..."
echo ""
composer_run test:unit || EXIT_CODES+=(1)

echo ""
echo "=========================================="

# Run PHP Integration Tests
echo "Running PHP Integration Tests (Pest)..."
echo ""
composer_run test:integration || EXIT_CODES+=(1)

echo ""
echo "=========================================="

# Run PHP Integration Tests (Multisite)
echo "Running PHP Integration Tests (Multisite)..."
echo ""
if [ -z "${WP_TESTS_DIR:-}" ] || [ ! -d "${WP_TESTS_DIR}" ]; then
  echo "⚠ Skipping multisite integration tests: WP_TESTS_DIR not set or not found."
else
  composer_run test:integration:multisite || EXIT_CODES+=(1)
fi

echo ""
echo "=========================================="

# Run JavaScript Tests
echo "Running JavaScript Tests (Vitest)..."
echo ""
npm run test:js || EXIT_CODES+=(1)

echo ""
echo "=========================================="

# Run E2E Tests
echo "Starting WordPress environment for E2E tests..."
npm run env:start || { echo "✗ Failed to start environment"; EXIT_CODES+=(1); exit 1; }
ENV_STARTED=1

echo ""
echo "Running E2E Tests (Playwright)..."
echo ""
npm run test:e2e || EXIT_CODES+=(1)

echo ""
echo "=========================================="
echo "Test Results Summary"
echo "=========================================="

if [ ${#EXIT_CODES[@]} -eq 0 ]; then
  echo "✓ All tests passed!"
  exit 0
else
  echo "✗ Some tests failed (${#EXIT_CODES[@]} test suite(s) failed)"
  exit 1
fi
