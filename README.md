Prometheus Helper
=================

Wrapper for PHP Prometheus library - https://github.com/PromPHP/prometheus_client_php

Install
-------

    composer require gupalo/prometheus-helper

Usage
-----

### Basic Usage

```php
use Gupalo\PrometheusHelper\FileAdapter;
use Gupalo\PrometheusHelper\Prometheus;

$helper = new Prometheus(new FileAdapter('/var/prom'), 'myapp');

// Counter
$helper->inc('requests_total', 'Total requests');
$helper->inc('requests_total', 'Total requests', ['method' => 'GET']);

// Gauge
$helper->gaugeSet(100, 'temperature', 'Current temperature');
$helper->gaugeInc('active_connections', 'Active connections');
$helper->gaugeDec('active_connections', 'Active connections');

// Histogram
$helper->observe(0.5, 'request_duration_seconds', 'Request duration');

// Render metrics
$response = $helper->render();
```

### With Redis Storage (DSN)

```php
use Gupalo\PrometheusHelper\Prometheus;
use Gupalo\PrometheusHelper\RedisAdapter;

$helper = new Prometheus(new RedisAdapter('redis://redis'), 'myapp');
$helper->inc('requests_total', 'Total requests');
```

Supported DSN formats:
- `redis://redis` - simple hostname
- `redis://redis:6380` - with port
- `redis://:password@redis:6379` - with password
- `redis://user:password@redis:6379/2` - with user, password and database
- `redis://redis:6379/0?timeout=0.5&persistent=true` - with options

### Symfony Integration

Configure in `config/services.yaml`:

```yaml
services:
    Prometheus\Storage\Adapter:
        class: Gupalo\PrometheusHelper\RedisAdapter
        arguments:
            $dsn: '%env(REDIS_DSN)%'
    Gupalo\PrometheusHelper\Prometheus:
        arguments:
            $adapter: '@Prometheus\Storage\Adapter'
            $namespace: 'myapp'

when@dev:
    services:
        Prometheus\Storage\Adapter:
            class: Gupalo\PrometheusHelper\FileAdapter
            arguments:
                $dir: '%kernel.project_dir%/var/prom'

when@test:
    services:
        Prometheus\Storage\Adapter:
            class: Prometheus\Storage\InMemory
```

Then inject in your services:

```php
class MyService
{
    public function __construct(
        private readonly Prometheus $prometheus,
    ) {}

    public function doSomething(): void
    {
        $this->prometheus->inc('operations_total', 'Total operations');
    }
}
```

Also see `tests` for more examples.
