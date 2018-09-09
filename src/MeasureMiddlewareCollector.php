<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use Rx\ObservableInterface;
use WyriHaximus\React\Inspector\CollectorInterface;
use WyriHaximus\React\Inspector\Metric;
use function ApiClients\Tools\Rx\observableFromArray;

final class MeasureMiddlewareCollector implements CollectorInterface
{
    /**
     * @var MeasureMiddleware[]
     */
    private $middlewares = [];

    public function register(string $key, MeasureMiddleware $middleware)
    {
        $this->middlewares[$key] = $middleware;
    }

    public function collect(): ObservableInterface
    {
        $metrics = [];

        /**
         * @var string            $key
         * @var MeasureMiddleware $middleware
         */
        foreach ($this->middlewares as $key => $middleware) {
            /** @var Metric $metric */
            foreach ($middleware->collect()->toArray() as $metric) {
                $metrics[] = new Metric(
                    $key . '.' . $metric->getKey(),
                    $metric->getValue(),
                    $metric->getTime()
                );
            }
        }

        return observableFromArray($metrics);
    }

    public function cancel(): void
    {
        $this->middlewares = [];
    }
}
