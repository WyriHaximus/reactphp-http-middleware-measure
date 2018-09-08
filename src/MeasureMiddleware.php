<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Rx\ObservableInterface;
use WyriHaximus\React\Inspector\CollectorInterface;
use WyriHaximus\React\Inspector\Metric;
use function ApiClients\Tools\Rx\observableFromArray;
use function React\Promise\resolve;

final class MeasureMiddleware implements CollectorInterface
{
    private const DEFAULT_PREFIX = '';

    /** @var string */
    private $prefix = self::DEFAULT_PREFIX;

    /** @var int */
    private $current = 0;

    /** @var int */
    private $total = 0;

    /** @var null|float */
    private $tookMin = null;

    /** @var float */
    private $tookMax = 0.0;

    /** @var float */
    private $tookAvg = 0.0;

    /** @var float */
    private $tookTotal = 0.0;

    public function __construct(string $prefix = self::DEFAULT_PREFIX)
    {
        $this->prefix = $prefix;
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $this->current++;
        $start = microtime(true);

        return resolve($next($request))->always(function () use ($start) {
            $took = microtime(true) - $start;
            $this->current--;
            $this->total++;
            $this->tookTotal += $took;

            if ($this->tookMin === null || $took < $this->tookMin) {
                $this->tookMin = $took;
            }

            if ($this->tookMax < $took) {
                $this->tookMax = $took;
            }

            $this->tookAvg = $this->tookTotal / $this->total;
        });
    }

    public function collect(): ObservableInterface
    {
        $metrics = [
            new Metric($this->prefix . 'current', $this->current),
            new Metric($this->prefix . 'total', $this->total),
            new Metric($this->prefix . 'took.min', $this->tookMin === null ? 0.0 : $this->tookMin),
            new Metric($this->prefix . 'took.max', $this->tookMax),
            new Metric($this->prefix . 'took.average', $this->tookAvg),
            new Metric($this->prefix . 'took.total', $this->tookTotal),
        ];

        $this->total = 0;
        $this->tookMin = null;
        $this->tookMax = 0.0;
        $this->tookAvg = 0.0;
        $this->tookTotal = 0;

        return observableFromArray($metrics);
    }

    public function cancel(): void
    {
        // Does not apply to this class
    }
}
