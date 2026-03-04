<?php

namespace LBHurtado\Contact\Traits;

use Illuminate\Database\Eloquent\Builder;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

trait HasMeta
{
    protected array $schemalessAttributes = ['meta'];

    public function initializeHasMeta(): void
    {
        $this->mergeFillable(['meta']);
        $this->mergeCasts([
            'meta' => SchemalessAttributes::class,
        ]);
        $this->setHidden(array_merge($this->getHidden(), ['meta']));
    }

    public function scopeWithMeta(): Builder
    {
        return $this->meta->modelScope();
    }
}
