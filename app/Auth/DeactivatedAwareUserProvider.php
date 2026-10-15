<?php

namespace App\Auth;

use App\Models\Scopes\ActiveUserScope;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The session guard's User provider. It must keep resolving a deactivated
 * User despite the default scope, so a session that survived deactivation is
 * still recognised and signed out with the proper message by
 * EnsureUserIsActive (ADR-0020), and a login attempt reaches the credential
 * check that refuses it.
 */
class DeactivatedAwareUserProvider extends EloquentUserProvider
{
    /**
     * @param  Model|null  $model
     * @return Builder<Model>
     */
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(ActiveUserScope::class);
    }
}
