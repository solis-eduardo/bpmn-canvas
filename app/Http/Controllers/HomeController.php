<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $diagrams = Diagram::query()
            ->when(! $user->is_admin, fn ($query) => $query->editableBy($user))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('home', compact('diagrams'));
    }
}
