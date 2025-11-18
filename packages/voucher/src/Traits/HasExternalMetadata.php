<?php

namespace LBHurtado\Voucher\Traits;

use LBHurtado\Voucher\Data\ExternalMetadataData;

/**
 * Trait HasExternalMetadata
 * 
 * Provides external metadata functionality for Voucher model.
 * Allows external systems to attach their own metadata to vouchers
 * using the existing metadata JSON field.
 * 
 * @property array $metadata
 */
trait HasExternalMetadata
{
    /**
     * Get external metadata as DTO
     */
    public function getExternalMetadataAttribute(): ?ExternalMetadataData
    {
        if (!isset($this->metadata['external'])) {
            return null;
        }
        
        return ExternalMetadataData::from($this->metadata['external']);
    }

    /**
     * Set external metadata from DTO or array
     */
    public function setExternalMetadataAttribute(ExternalMetadataData|array|null $value): void
    {
        if ($value === null) {
            $metadata = $this->metadata ?? [];
            unset($metadata['external']);
            $this->metadata = $metadata;
            return;
        }
        
        $metadata = $this->metadata ?? [];
        $metadata['external'] = $value instanceof ExternalMetadataData 
            ? $value->toArray() 
            : $value;
        $this->metadata = $metadata;
    }

    /**
     * Query scope: Filter by external metadata field
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field Field name within external metadata
     * @param mixed $value Value to match
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereExternal($query, string $field, mixed $value)
    {
        return $query->whereJsonContains("metadata->external->{$field}", $value);
    }

    /**
     * Query scope: Filter by multiple external metadata values
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field Field name within external metadata
     * @param array $values Values to match
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereExternalIn($query, string $field, array $values)
    {
        return $query->where(function ($q) use ($field, $values) {
            foreach ($values as $value) {
                $q->orWhereJsonContains("metadata->external->{$field}", $value);
            }
        });
    }
}
