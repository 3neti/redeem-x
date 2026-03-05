<?php

declare(strict_types=1);

namespace LBHurtado\OgMeta\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LBHurtado\OgMeta\Contracts\OgMetaResolver;
use LBHurtado\OgMeta\Data\OgMetaData;

/**
 * Base class for model-backed OG meta resolvers.
 *
 * Handles model lookup boilerplate. Subclasses only need to implement
 * mapToOgData() to define how model fields map to OG card elements.
 */
abstract class ModelOgResolver implements OgMetaResolver
{
    /** @var class-string<Model> The Eloquent model class */
    protected string $model;

    /** Column to look up the model by */
    protected string $findBy = 'code';

    /** Request query parameter name */
    protected string $queryParam = 'code';

    /** Whether to uppercase the lookup value */
    protected bool $uppercase = false;

    public function resolve(Request $request): ?OgMetaData
    {
        $value = $request->query($this->queryParam);

        if (! $value) {
            return null;
        }

        $model = $this->findModel($value);

        if (! $model) {
            return null;
        }

        return $this->mapToOgData($model);
    }

    public function resolveForImage(string $identifier): ?OgMetaData
    {
        $model = $this->findModel($identifier);

        if (! $model) {
            return null;
        }

        return $this->mapToOgData($model);
    }

    /**
     * Map the model to OG metadata. Subclasses must implement this.
     */
    abstract protected function mapToOgData(Model $model): OgMetaData;

    protected function findModel(string $value): ?Model
    {
        $value = $this->uppercase ? strtoupper(trim($value)) : trim($value);

        return ($this->model)::where($this->findBy, $value)->first();
    }
}
