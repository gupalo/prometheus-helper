Prometheus Helper
=================

Wrapper for PHP Prometheus library - `promphp/prometheus_client_php`

Install
-------

    composer require gupalo/prometheus-helper

Use
---
```
    public static function registration(bool $isSuccess): void
    {
        if ($isSuccess) {
            PrometheusHelper::inc('analytics_registration_success_total', 'registration success');
        } else {
            PrometheusHelper::inc('analytics_registration_error_total', 'registration error');
        }
    }
```

Also see `tests`.

If you want to set custom directory for cache - `PrometheusHelper::setDir('/your/cache/dir/for/prom')`.
