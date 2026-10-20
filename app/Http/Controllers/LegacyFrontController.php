<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class LegacyFrontController extends Controller
{
    public function __invoke(Request $request): never
    {
        require_once base_path('legacy/front.php');
        carapi_handle_request();
        exit;
    }
}
