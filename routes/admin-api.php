<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DocsController;
use App\Http\Controllers\Admin\MeController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Middleware\ForceJson;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'internal-docs'])->group(function (): void {
    Route::get('/api/docs', [DocsController::class, 'ui'])->name('admin.docs');
    Route::get('/api/docs/openapi.json', [DocsController::class, 'spec'])->name('admin.docs.spec');
});

Route::middleware([ForceJson::class, SecurityHeaders::class, 'auth:sanctum', 'admin', 'throttle:300,1'])->prefix('api/admin')->group(function (): void {
    Route::get('me', MeController::class)->name('admin.me');

    foreach ([
        'pages' => PageController::class,
        'content' => ContentController::class,
        'products' => ProductController::class,
        'plans' => PlanController::class,
        'announcements' => AnnouncementController::class,
    ] as $path => $controller) {
        Route::get($path, [$controller, 'index'])->name('admin.'.$path.'.index');
        Route::post($path, [$controller, 'store'])->name('admin.'.$path.'.store');
        Route::get($path.'/{id}', [$controller, 'show'])->whereNumber('id')->name('admin.'.$path.'.show');
        Route::match(['put', 'patch'], $path.'/{id}', [$controller, 'update'])->whereNumber('id')->name('admin.'.$path.'.update');
        Route::delete($path.'/{id}', [$controller, 'destroy'])->whereNumber('id')->name('admin.'.$path.'.destroy');
    }

    Route::get('media', [MediaController::class, 'index'])->name('admin.media.index');
    Route::post('media', [MediaController::class, 'store'])->name('admin.media.store');
    Route::get('media/{id}', [MediaController::class, 'show'])->whereNumber('id')->name('admin.media.show');
    Route::get('media/{id}/file', [MediaController::class, 'file'])->whereNumber('id')->name('admin.media.file');
    Route::match(['put', 'patch'], 'media/{id}', [MediaController::class, 'update'])->whereNumber('id')->name('admin.media.update');
    Route::delete('media/{id}', [MediaController::class, 'destroy'])->whereNumber('id')->name('admin.media.destroy');

    Route::get('settings', [SettingController::class, 'index'])->name('admin.settings.index');
    Route::post('settings', [SettingController::class, 'store'])->name('admin.settings.store');
    Route::get('settings/{key}', [SettingController::class, 'show'])->where('key', '[A-Za-z0-9_.:-]+')->name('admin.settings.show');
    Route::match(['put', 'patch'], 'settings/{key}', [SettingController::class, 'update'])->where('key', '[A-Za-z0-9_.:-]+')->name('admin.settings.update');
    Route::delete('settings/{key}', [SettingController::class, 'destroy'])->where('key', '[A-Za-z0-9_.:-]+')->name('admin.settings.destroy');

    $catalog = [
        'brands' => 'brands',
        'models' => 'models',
        'generations' => 'generations',
        'cars' => 'modifications',
        'equipment' => 'modification_equipment',
        'model-photos' => 'model_photos',
        'photos' => 'photos',
    ];
    foreach ($catalog as $path => $table) {
        Route::get($path, [CatalogController::class, 'index'])->defaults('table', $table)->name('admin.'.$path.'.index');
        Route::post($path, [CatalogController::class, 'store'])->defaults('table', $table)->name('admin.'.$path.'.store');
        Route::get($path.'/{id}', [CatalogController::class, 'show'])->defaults('table', $table)->whereNumber('id')->name('admin.'.$path.'.show');
        Route::match(['put', 'patch'], $path.'/{id}', [CatalogController::class, 'update'])->defaults('table', $table)->whereNumber('id')->name('admin.'.$path.'.update');
        Route::delete($path.'/{id}', [CatalogController::class, 'destroy'])->defaults('table', $table)->whereNumber('id')->name('admin.'.$path.'.destroy');
    }

    Route::get('cars/{parentId}/equipment', [CatalogController::class, 'index'])
        ->defaults('table', 'modification_equipment')
        ->defaults('parentColumn', 'modification_id')
        ->whereNumber('parentId')
        ->name('admin.cars.equipment.index');
    Route::post('cars/{parentId}/equipment', [CatalogController::class, 'store'])
        ->defaults('table', 'modification_equipment')
        ->defaults('parentColumn', 'modification_id')
        ->whereNumber('parentId')
        ->name('admin.cars.equipment.store');
    Route::get('cars/{id}/specs', [CatalogController::class, 'specs'])->whereNumber('id')->name('admin.cars.specs.show');
    Route::put('cars/{id}/specs', [CatalogController::class, 'saveSpecs'])->whereNumber('id')->name('admin.cars.specs.update');
});
