<?php

declare(strict_types=1);

namespace LBHurtado\OgMeta\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LBHurtado\OgMeta\Services\OgMetaService;
use Symfony\Component\HttpFoundation\Response;

class InjectOgMeta
{
    public function __construct(
        private readonly OgMetaService $service,
    ) {}

    /**
     * @param  string|null  $resolverKey  The resolver key from middleware parameter (e.g. 'disburse')
     */
    public function handle(Request $request, Closure $next, ?string $resolverKey = null): Response
    {
        if (! $resolverKey) {
            return $next($request);
        }

        $data = $this->service->resolveByKey($resolverKey, $request);

        if ($data) {
            view()->share('og', $data->toViewData());
        }

        return $next($request);
    }
}
