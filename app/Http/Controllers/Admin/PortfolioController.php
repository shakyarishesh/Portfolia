<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

/** Placeholder — implemented in the admin portfolio-management phase. */
class PortfolioController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(501);
    }
}
