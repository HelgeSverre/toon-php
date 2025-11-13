# Toon - Token-Oriented Object Notation
# https://just.systems/man/en/
# Load environment variables from .env

set dotenv-load := true

# Show available commands by default (first recipe is default)
help:
    @just --list

# Private: Ensure vendor directory exists, auto-install if missing
[private]
_ensure-vendor:
    #!/usr/bin/env bash
    if [ ! -d vendor ]; then
        if command -v composer &> /dev/null; then
            echo "📦 Installing dependencies..."
            composer install
        else
            echo "❌ vendor/ not found and composer not available"
            exit 1
        fi
    fi

# === Setup ===

[doc('Install PHP dependencies')]
[group('setup')]
install:
    @echo "Installing PHP dependencies..."
    composer install

# === Specification Sync ===

[doc('Download latest SPEC.md from upstream')]
[group('spec')]
sync-spec:
    @echo "Downloading SPEC.md from toon-format/spec..."
    @curl -fsSL https://raw.githubusercontent.com/toon-format/spec/main/SPEC.md -o docs/SPEC.md
    @curl -fsSL https://raw.githubusercontent.com/toon-format/spec/main/CHANGELOG.md -o docs/SPEC.md
    @echo "Done! Review changes: git diff docs/SPEC.md"

# === Testing ===

[doc('Run all tests')]
[group('test')]
test: _ensure-vendor
    @echo "Running tests..."
    composer test

[doc('Run tests with coverage report')]
[group('test')]
coverage: _ensure-vendor
    #!/usr/bin/env bash
    if command -v herd &> /dev/null; then
        echo "Running tests with coverage (Herd)..."
        composer coverage:herd
    else
        echo "Running tests with coverage..."
        composer coverage
    fi

[doc('Watch files and run tests on change (requires entr)')]
[group('test')]
watch-test: _ensure-vendor
    @echo "Watching for changes... (press Ctrl+C to stop)"
    @find src tests -name '*.php' | entr -c composer test

# === Development Tools ===

[doc('Run PHPStan static analysis')]
[group('dev')]
analyse: _ensure-vendor
    @echo "Running PHPStan analysis..."
    composer analyse

[doc('Auto-fix code style issues')]
[group('dev')]
fix: _ensure-vendor
    @echo "Fixing PHP code style..."
    composer format

# Hidden aliases for common spellings/tools
[private]
analyze: analyse

[private]
format: fix

[private]
pint: fix

# === Workflows ===

[doc('Quick dev cycle: format and test')]
[group('workflow')]
dev: fix test
    @echo "Development cycle complete!"

[doc('Full quality suite: format + analyse + test')]
[group('workflow')]
quality: fix analyse test
    @echo "Quality checks complete!"

[doc('Prepare for PR: run full quality suite')]
[group('workflow')]
pr: quality
    @echo "Ready for PR!"

[doc('CI pipeline: analyse + test (no formatting)')]
[group('workflow')]
ci: analyse test
    @echo "CI pipeline complete!"

[doc('Quick check: analyse only (no test)')]
[group('workflow')]
quick: analyse
    @echo "Quick check complete!"

[doc('Watch mode: format + test on file changes (requires entr)')]
[group('workflow')]
watch: _ensure-vendor
    @echo "Watching for changes (format + test)... (press Ctrl+C to stop)"
    @find src tests -name '*.php' | entr -c bash -c 'just fix && just test'

[doc('Clean cache and generated files')]
[group('workflow')]
clean:
    @echo "Cleaning cache files..."
    @rm -rf vendor/bin/.phpunit.result.cache
    @rm -rf .phpunit.cache
    @rm -rf coverage
    @echo "Cache cleaned!"

# Deprecated alias - use 'quality' instead
[private]
check: ci

# === Benchmarks ===

[doc('Run token efficiency benchmarks')]
[group('benchmark')]
benchmark:
    @echo "Running token efficiency benchmarks..."
    @cd benchmarks && composer benchmark

[doc('Run performance benchmarks (time, memory, throughput)')]
[group('benchmark')]
benchmark-performance: _ensure-vendor
    @echo "Running PHPBench performance benchmarks..."
    @./vendor/bin/phpbench run --report=default

[doc('Run performance benchmarks with summary report')]
[group('benchmark')]
benchmark-perf-summary: _ensure-vendor
    @echo "Running PHPBench with summary report..."
    @./vendor/bin/phpbench run --report=summary

[doc('Run all benchmarks (token + performance)')]
[group('benchmark')]
benchmark-all: benchmark benchmark-performance
    @echo "All benchmarks complete!"

[doc('Compare performance against baseline')]
[group('benchmark')]
benchmark-compare: _ensure-vendor
    @echo "Comparing against baseline..."
    @./vendor/bin/phpbench report --ref=baseline --report=default

[doc('Store current performance as baseline')]
[group('benchmark')]
benchmark-baseline: _ensure-vendor
    @echo "Storing baseline results..."
    @./vendor/bin/phpbench run --report=summary --store --tag=baseline

[doc('Setup and run all benchmarks')]
[group('benchmark')]
benchmark-full: benchmark-install benchmark-all
    @echo "All benchmarks complete!"

[doc('Install benchmark dependencies')]
[group('benchmark')]
benchmark-install:
    @echo "Installing benchmark dependencies..."
    @cd benchmarks && composer install
