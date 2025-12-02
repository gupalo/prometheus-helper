# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Run a single test
./vendor/bin/phpunit tests/FileAdapterTest.php
./vendor/bin/phpunit --filter testCreateAndRestore
```

## Architecture

This is a PHP library that provides a simplified wrapper around the [promphp/prometheus_client_php](https://github.com/PromPHP/prometheus_client_php) library for Prometheus metrics.

### Key Components

- **Prometheus** (`src/Prometheus.php`) - Injectable service providing simple methods for Prometheus metrics:
  - Constructor accepts any `Prometheus\Storage\Adapter` and optional namespace
  - `inc()`, `incBy()` - Counter operations
  - `set()`, `gaugeInc()`, `gaugeIncBy()`, `gaugeDec()`, `gaugeDecBy()`, `gaugeSet()` - Gauge operations
  - `observe()` - Histogram operations
  - `render()` - Returns Symfony Response with metrics in Prometheus text format

- **FileAdapter** (`src/FileAdapter.php`) - Storage adapter that persists metrics to a JSON file (`data.json`). Extends JsonAdapter and flushes on destruction.

- **RedisAdapter** (`src/RedisAdapter.php`) - Redis storage adapter with DSN support. Extends promphp Redis adapter and parses DSN like `redis://user:pass@host:port/database`.

- **JsonAdapter** (`src/JsonAdapter.php`) - Extends Prometheus InMemory adapter with JSON serialization support.

### Storage Adapters

The library supports any adapter implementing `Prometheus\Storage\Adapter`:
- `FileAdapter` - JSON file-based storage (included)
- `RedisAdapter` - Redis storage with DSN support (included, requires ext-redis)
- `Prometheus\Storage\InMemory` - In-memory storage (from promphp library)
- `Prometheus\Storage\APC` - APCu storage (from promphp library)
