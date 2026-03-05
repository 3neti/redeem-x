<?php

declare(strict_types=1);

namespace LBHurtado\OgMeta\Contracts;

use Illuminate\Http\Request;
use LBHurtado\OgMeta\Data\OgMetaData;

/**
 * Contract for OG meta resolvers.
 *
 * Each resolver knows how to produce OG metadata for a specific route group.
 * Resolvers can pull data from any source: models, APIs, session, config, etc.
 */
interface OgMetaResolver
{
    /**
     * Produce OG metadata for the current page request, or null to skip.
     */
    public function resolve(Request $request): ?OgMetaData;

    /**
     * Produce OG metadata for an image request (used by the image-serving endpoint).
     *
     * @param  string  $identifier  The identifier from the URL (e.g. voucher code)
     */
    public function resolveForImage(string $identifier): ?OgMetaData;
}
