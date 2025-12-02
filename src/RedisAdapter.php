<?php

declare(strict_types=1);

namespace Gupalo\PrometheusHelper;

use InvalidArgumentException;
use Prometheus\Storage\Redis;

class RedisAdapter extends Redis
{
    public function __construct(string $dsn)
    {
        parent::__construct(self::parseDsn($dsn));
    }

    public static function parseDsn(string $dsn): array
    {
        $parsed = parse_url($dsn);

        if ($parsed === false || !isset($parsed['scheme']) || !in_array($parsed['scheme'], ['redis', 'rediss'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid Redis DSN: %s', $dsn));
        }

        $options = [
            'host' => $parsed['host'] ?? '127.0.0.1',
            'port' => $parsed['port'] ?? 6379,
        ];

        if (isset($parsed['user']) && $parsed['user'] !== '') {
            $options['user'] = $parsed['user'];
        }

        if (isset($parsed['pass'])) {
            $options['password'] = $parsed['pass'];
        }

        if (isset($parsed['path']) && $parsed['path'] !== '' && $parsed['path'] !== '/') {
            $database = ltrim($parsed['path'], '/');
            if (is_numeric($database)) {
                $options['database'] = (int) $database;
            }
        }

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);

            if (isset($query['timeout'])) {
                $options['timeout'] = (float) $query['timeout'];
            }
            if (isset($query['read_timeout'])) {
                $options['read_timeout'] = $query['read_timeout'];
            }
            if (isset($query['persistent'])) {
                $options['persistent_connections'] = filter_var($query['persistent'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $options;
    }
}
