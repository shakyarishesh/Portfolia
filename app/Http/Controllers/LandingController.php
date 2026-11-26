<?php

namespace App\Http\Controllers;

use App\Models\PortfolioType;
use App\Support\Templates;
use Illuminate\Http\Request;

/** "/": what the platform offers, the template gallery and the session-only demo form (with a portfolio type). */
class LandingController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('site.landing', [
            'templates' => Templates::available(),
            'demo' => $request->session()->get('demo', []),
            'types' => PortfolioType::active()->ordered()->get(),
            'disciplineOptions' => PortfolioType::disciplineOptions(),
        ]);
    }
}
