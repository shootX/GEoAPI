<?php

declare(strict_types=1);

use App\Http\Controllers\LegacyFrontController;
use Illuminate\Support\Facades\Route;

$exact = [
    'admin',
    'auth/me',
    'email/verification/resend',
    'email/verify',
    'reset-password',
    'getimage',
    'getImage',
];

foreach ($exact as $path) {
    Route::any($path, LegacyFrontController::class);
}

Route::any('docs/{path}', LegacyFrontController::class)->where('path', '.*');
Route::any('account/projects/{path}', LegacyFrontController::class)->where('path', '.*');
Route::any('admin/users/{path?}', LegacyFrontController::class)->where('path', '.*');
Route::any('admin/projects/{path?}', LegacyFrontController::class)->where('path', '.*');
Route::any('api/v1/{path?}', LegacyFrontController::class)->where('path', '.*');
Route::any('api/picker/{path?}', LegacyFrontController::class)->where('path', '.*');
Route::any('api/models/{id}/photo', LegacyFrontController::class)->where('id', '[^/]+');
Route::any('widget/v1/{path?}', LegacyFrontController::class)->where('path', '.*');
