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

- **PrometheusHelper** (`src/PrometheusHelper.php`) - Static facade providing simple methods for Prometheus metrics:
  - `inc()`, `incBy()` - Counter operations
  - `set()`, `gaugeInc()`, `gaugeIncBy()`, `gaugeDec()`, `gaugeDecBy()`, `gaugeSet()` - Gauge operations
  - `observe()` - Histogram operations
  - `render()` - Returns Symfony Response with metrics in Prometheus text format

- **FileAdapter** (`src/FileAdapter.php`) - Storage adapter that persists metrics to a JSON file (`data.json`). Extends JsonAdapter and flushes on destruction.

- **JsonAdapter** (`src/JsonAdapter.php`) - Extends Prometheus InMemory adapter with JSON serialization support.

### Configuration

- `PrometheusHelper::$namespace` - Metric namespace (default: 'app')
- `PrometheusHelper::setDir()` - Set custom storage directory
- `PrometheusHelper::$isEnabled` - Toggle metrics collection on/off

Default storage directories: `/app/var/prom` or `/tmp/prom`
