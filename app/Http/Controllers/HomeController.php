<?php

namespace App\Http\Controllers;

use App\Repositories\DiagramRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController
{
    public function __construct(private readonly DiagramRepository $diagrams) {}

    public function __invoke(Request $request): View
    {
        $diagrams = $this->diagrams->recentFor($request->user());

        return view('home', compact('diagrams'));
    }
}
