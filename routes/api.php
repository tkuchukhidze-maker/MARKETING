<?php

use App\Http\Controllers\Reporting\IngestController;
use Illuminate\Support\Facades\Route;

// POS / booking systems push daily sales here (token = the source's secret)
Route::post('/reporting/ingest/{token}', IngestController::class)
    ->middleware('throttle:60,1')
    ->name('reporting.ingest');
