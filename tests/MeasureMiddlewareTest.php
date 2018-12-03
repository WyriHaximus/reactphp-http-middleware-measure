<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use ApiClients\Tools\TestUtilities\TestCase;
use Cake\Collection\Collection;
use React\Promise\Deferred;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;
use WyriHaximus\React\Http\Middleware\MeasureMiddleware;

/**
 * @internal
 */
final class MeasureMiddlewareTest extends TestCase
{
    public function testMetrics(): void
    {
        $map = function ($value, $key) {
            return [
                'key' => $key,
                'value' => $value,
            ];
        };
        $middleware = new MeasureMiddleware();

        $metrics = \iterator_to_array($middleware->collect());
        self::assertCount(6, $metrics);

        foreach ($metrics as $key => $value) {
            self::assertSame(0.0, (float)$value, $key);
        }

        $deferred = new Deferred();
        $middleware(new ServerRequest('GET', '/'), function () use ($deferred) {
            return $deferred->promise();
        });

        $metrics = \iterator_to_array($middleware->collect());

        $current = (new Collection($metrics))->map($map)->filter(function (array $metric) {
            return $metric['key'] === 'current';
        })->first();
        self::assertSame(1, $current['value'], $current['key']);

        $theRest = (new Collection($metrics))->map($map)->filter(function (array $metric) {
            return $metric['key'] !== 'current';
        })->toArray();

        foreach ($theRest as $metric) {
            self::assertSame(0.0, (float)$metric['value'], $metric['key']);
        }

        $deferred->resolve(new Response());

        $metrics = \iterator_to_array($middleware->collect());

        $current = (new Collection($metrics))->map($map)->filter(function (array $metric) {
            return $metric['key'] === 'current';
        })->first();
        self::assertSame(0, $current['value'], $current['key']);

        $total = (new Collection($metrics))->map($map)->filter(function (array $metric) {
            return $metric['key'] === 'total';
        })->first();
        self::assertSame(1, $total['value'], $total['key']);

        $theRest = (new Collection($metrics))->map($map)->filter(function (array $metric) {
            return $metric['key'] !== 'current' && $metric['key'] !== 'total';
        })->toArray();

        foreach ($theRest as $metric) {
            self::assertTrue(0.0 < (float)$metric['value'], $metric['key']);
        }
    }
}
