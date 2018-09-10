<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use WyriHaximus\React\Inspector\Metric;
use function React\Promise\resolve;

final class MeasureMiddleware
{
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

    public function collect(): iterable
    {
        yield new Metric('current', $this->current);
        yield new Metric('total', $this->total);
        yield new Metric('took.min', $this->tookMin === null ? 0.0 : $this->tookMin);
        yield new Metric('took.max', $this->tookMax);
        yield new Metric('took.average', $this->tookAvg);
        yield new Metric('took.total', $this->tookTotal);

        $this->total = 0;
        $this->tookMin = null;
        $this->tookMax = 0.0;
        $this->tookAvg = 0.0;
        $this->tookTotal = 0;
    }

    public function cancel(): void
    {
        // Does not apply to this class
    }
}
