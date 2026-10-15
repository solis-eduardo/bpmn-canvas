<?php

namespace App\Support;

/**
 * The access questions a Diagram answers about a User that AccessMemo
 * memoizes.
 */
enum AccessPredicate: string
{
    case Editable = 'editable';
    case Collaborator = 'collaborator';
    case Manager = 'manager';
}
