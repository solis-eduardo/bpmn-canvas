<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * The default scope of User (ADR-0020): every query returns active Users only,
 * so no listing, picker, lookup or validation has to remember the filter.
 * Only User administration and the places that must still find a deactivated
 * account opt out, through User::withDeactivated().
 */
class ActiveUserScope implements Scope
{
    public const COLUMN = 'deactivated_at';

    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->qualifyColumn(self::COLUMN));
    }
}
