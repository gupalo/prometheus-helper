<?php

namespace Gupalo\PrometheusHelper\Tests;

use Gupalo\PrometheusHelper\RedisAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RedisAdapterTest extends TestCase
{
    public function testParseDsnSimple(): void
    {
        $options = RedisAdapter::parseDsn('redis://redis');

        self::assertSame('redis', $options['host']);
        self::assertSame(6379, $options['port']);
        self::assertArrayNotHasKey('password', $options);
        self::assertArrayNotHasKey('database', $options);
    }

    public function testParseDsnWithPort(): void
    {
        $options = RedisAdapter::parseDsn('redis://localhost:6380');

        self::assertSame('localhost', $options['host']);
        self::assertSame(6380, $options['port']);
    }

    public function testParseDsnWithPassword(): void
    {
        $options = RedisAdapter::parseDsn('redis://:secret@redis:6379');

        self::assertSame('redis', $options['host']);
        self::assertSame(6379, $options['port']);
        self::assertSame('secret', $options['password']);
        self::assertArrayNotHasKey('user', $options);
    }

    public function testParseDsnWithUserAndPassword(): void
    {
        $options = RedisAdapter::parseDsn('redis://user:password@redis:6379');

        self::assertSame('redis', $options['host']);
        self::assertSame(6379, $options['port']);
        self::assertSame('user', $options['user']);
        self::assertSame('password', $options['password']);
    }

    public function testParseDsnWithDatabase(): void
    {
        $options = RedisAdapter::parseDsn('redis://redis:6379/2');

        self::assertSame('redis', $options['host']);
        self::assertSame(6379, $options['port']);
        self::assertSame(2, $options['database']);
    }

    public function testParseDsnWithQueryParams(): void
    {
        $options = RedisAdapter::parseDsn('redis://redis:6379/0?timeout=0.5&read_timeout=5&persistent=true');

        self::assertSame('redis', $options['host']);
        self::assertSame(6379, $options['port']);
        self::assertSame(0, $options['database']);
        self::assertSame(0.5, $options['timeout']);
        self::assertSame('5', $options['read_timeout']);
        self::assertTrue($options['persistent_connections']);
    }

    public function testParseDsnFull(): void
    {
        $options = RedisAdapter::parseDsn('redis://admin:supersecret@redis.example.com:6380/5?timeout=1.0');

        self::assertSame('redis.example.com', $options['host']);
        self::assertSame(6380, $options['port']);
        self::assertSame('admin', $options['user']);
        self::assertSame('supersecret', $options['password']);
        self::assertSame(5, $options['database']);
        self::assertSame(1.0, $options['timeout']);
    }

    public function testParseDsnRediss(): void
    {
        $options = RedisAdapter::parseDsn('rediss://redis:6379');

        self::assertSame('redis', $options['host']);
        self::assertSame(6379, $options['port']);
    }

    public function testParseDsnInvalidScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Redis DSN');

        RedisAdapter::parseDsn('http://redis:6379');
    }

    public function testParseDsnInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Redis DSN');

        RedisAdapter::parseDsn('not a url');
    }
}
