<?php

namespace App\Http\Controllers;

use App\Support\Templates;
use Illuminate\Http\Request;

/** "/": what the platform offers, the template gallery and the session-only demo form. */
class LandingController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('site.landing', [
            'templates' => Templates::available(),
            'demo' => $request->session()->get('demo', []),
        ]);
    }
}
