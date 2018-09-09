<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use ApiClients\Tools\TestUtilities\TestCase;
use React\Promise\Deferred;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;
use Rx\React\Promise;
use WyriHaximus\React\Http\Middleware\MeasureMiddleware;
use WyriHaximus\React\Inspector\Metric;

final class MeasureMiddlewareTest extends TestCase
{
    public function testMetrics()
    {
        $middleware = new MeasureMiddleware();

        $metrics = $this->await(Promise::fromObservable($middleware->collect()->toArray()));
        self::assertCount(6, $metrics);

        /** @var Metric $metric */
        foreach ($metrics as $metric) {
            self::assertSame(0.0, $metric->getValue(), $metric->getKey());
        }

        $deferred = new Deferred();
        $middleware(new ServerRequest('GET', '/'), function () use ($deferred) {
            return $deferred->promise();
        });

        $metrics = $middleware->collect();

        /** @var Metric $current */
        $current = $this->await(Promise::fromObservable($metrics->filter(function (Metric $metric) {
            return $metric->getKey() === 'current';
        })));
        self::assertSame(1.0, $current->getValue(), $current->getKey());

        /** @var Metric $current */
        $theRest = $this->await(Promise::fromObservable($metrics->filter(function (Metric $metric) {
            return $metric->getKey() !== 'current';
        })->toArray()));

        /** @var Metric $metric */
        foreach ($theRest as $metric) {
            self::assertSame(0.0, $metric->getValue(), $metric->getKey());
        }

        $deferred->resolve(new Response());

        $metrics = $middleware->collect();

        /** @var Metric $current */
        $current = $this->await(Promise::fromObservable($metrics->filter(function (Metric $metric) {
            return $metric->getKey() === 'current';
        })));
        self::assertSame(0.0, $current->getValue(), $current->getKey());

        /** @var Metric $current */
        $total = $this->await(Promise::fromObservable($metrics->filter(function (Metric $metric) {
            return $metric->getKey() === 'total';
        })));
        self::assertSame(1.0, $total->getValue(), $total->getKey());

        /** @var Metric $current */
        $theRest = $this->await(Promise::fromObservable($metrics->filter(function (Metric $metric) {
            return $metric->getKey() !== 'current' && $metric->getKey() !== 'total';
        })->toArray()));

        /** @var Metric $metric */
        foreach ($theRest as $metric) {
            self::assertTrue(0.0 < $metric->getValue(), $metric->getKey());
        }
    }
}
