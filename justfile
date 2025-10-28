# Toon - Token-Oriented Object Notation
# https://just.systems/man/en/

# Load environment variables from .env
set dotenv-load

# Show available commands by default (first recipe is default)
help:
    @just --list

# === Setup ===

[group('setup')]
install:
    @echo "Installing PHP dependencies..."
    composer install

[group('setup')]
[doc('Complete initial setup')]
setup: install
    @echo "Setup complete!"
    @echo ""
    @echo "Try running: just test"

# === Testing ===

[group('test')]
[doc('Run all tests')]
test:
    @echo "Running tests..."
    composer test

[group('test')]
[doc('Run tests with coverage report')]
test-coverage:
    @echo "Running tests with coverage..."
    composer coverage

[group('test')]
[doc('Run tests with coverage using Herd')]
test-coverage-herd:
    @echo "Running tests with coverage (Herd)..."
    composer coverage:herd

[group('test')]
[doc('Watch files and run tests on change (requires entr)')]
watch-test:
    @echo "Watching for changes... (press Ctrl+C to stop)"
    @find src tests -name '*.php' | entr -c composer test

# === Static Analysis ===

[group('analyse')]
[doc('Run PHPStan static analysis')]
analyse:
    @echo "Running PHPStan analysis..."
    composer analyse

[group('analyse')]
[doc('Alias for analyse')]
analyze: analyse

# === Code Style ===

[group('format')]
[doc('Auto-fix code style issues')]
fix:
    @echo "Fixing PHP code style..."
    composer format

[group('format')]
[doc('Alias for fix')]
format: fix

[group('format')]
[doc('Alias for fix')]
pint: fix

# === Quality Checks ===

[group('quality')]
[doc('Run all checks (format + analyse + test)')]
quality: fix analyse test
    @echo "Quality checks complete!"

[group('quality')]
[doc('Run checks without modifying files')]
check: analyse test
    @echo "Checks complete!"

# === Benchmarks ===

[group('benchmark')]
[doc('Run token efficiency benchmarks')]
benchmark:
    @echo "Running benchmarks..."
    @cd benchmarks && composer benchmark

[group('benchmark')]
[doc('Setup and run benchmarks')]
benchmark-full: benchmark-install benchmark
    @echo "Benchmark complete!"

[group('benchmark')]
[doc('Install benchmark dependencies')]
benchmark-install:
    @echo "Installing benchmark dependencies..."
    @cd benchmarks && composer install

# === Cleanup ===

[group('workflow')]
[doc('Clean cache and generated files')]
clean:
    @echo "Cleaning cache files..."
    @rm -rf vendor/bin/.phpunit.result.cache
    @rm -rf .phpunit.cache
    @rm -rf coverage
    @echo "Cache cleaned!"

# === CI/CD ===

[group('ci')]
[doc('Run CI pipeline (analyse + test)')]
ci: analyse test
    @echo "CI pipeline complete!"

# === Development Workflows ===

[group('workflow')]
[doc('Quick dev cycle: fix code style and run tests')]
dev: fix test
    @echo "Development cycle complete!"

[group('workflow')]
[doc('Prepare for PR: run full quality suite')]
pr: quality
    @echo "Ready for PR!"

[group('workflow')]
[doc('Quick check without running tests')]
quick: analyse
    @echo "Quick check complete!"