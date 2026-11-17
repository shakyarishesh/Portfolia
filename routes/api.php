<?php

use App\Http\Controllers\Api\DemoController;
use Illuminate\Support\Facades\Route;

// Public sandbox for the API playground: sample data only, rate limited, no auth or state.
Route::prefix('demo')->middleware('throttle:demo-api')->group(function () {
    Route::get('/employees', [DemoController::class, 'employees']);
    Route::get('/employees/{id}', [DemoController::class, 'employee'])->whereNumber('id');
    Route::post('/payroll/calculate', [DemoController::class, 'payroll']);
});
