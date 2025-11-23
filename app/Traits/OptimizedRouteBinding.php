<?php

namespace App\Traits;

trait OptimizedRouteBinding
{
    /**
     * Columns to select for route model binding.
     * Override this property in your model.
     */
    protected array $routeBindingColumns = ['*'];

    /**
     * Retrieve the model for a bound value with optimized column selection.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->where($field ?? $this->getRouteKeyName(), $value);

        // Only select specific columns if defined
        if ($this->routeBindingColumns !== ['*']) {
            $query->select($this->routeBindingColumns);
        }

        return $query->firstOrFail();
    }

    /**
     * Retrieve the child model for a bound value with optimized column selection.
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return parent::resolveChildRouteBinding($childType, $value, $field);
    }
}
