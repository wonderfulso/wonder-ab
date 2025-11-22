<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use pivotalso\PivotalAb\Http\Middleware\WonderAbAuthMiddleware;
use pivotalso\PivotalAb\Jobs\GetLists;
use pivotalso\PivotalAb\Jobs\GetReport;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

if (config('laravel-ab.report_url')) {
    $middleware = [WonderAbAuthMiddleware::class];

    // Add custom middleware if configured
    $customMiddleware = config('laravel-ab.report_middleware', []);
    if (! empty($customMiddleware)) {
        $middleware = array_merge($middleware, $customMiddleware);
    }

    Route::middleware($middleware)->group(function () {
        $path = config('laravel-ab.report_url');

        $url = sprintf('%s/{id?}', $path);
        Route::get($url, function (Request $request) use ($path) {
            $id = $request->get('id', null);
            $reports = dispatch_sync(new GetLists);
            $experiments = [];

            foreach ($reports as $report) {
                $experiments[] = [
                    'id' => $report->id,
                    'name' => $report->experiment,
                    'conditions' => dispatch_sync(new GetReport($report->experiment)),
                ];
            }

            if ($id) {
                $experiment = current(array_filter($experiments, function ($experiment) use ($id) {
                    return $experiment['id'] == $id;
                })) ?: ($experiments[0] ?? null);
            } else {
                $experiment = $experiments[0] ?? null;
            }

            return view('laravel-ab::report', compact('experiments', 'experiment', 'path', 'id'));
        })->name('pivotal-ab.report');
    });
}
