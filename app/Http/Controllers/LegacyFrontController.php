<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LegacyFrontController extends Controller
{
    public function __invoke(Request $request): never
    {
        require_once base_path('legacy/front.php');
        if (app()->runningUnitTests()) {
            $_SERVER['REQUEST_URI'] = $request->getRequestUri();
            $_SERVER['REQUEST_METHOD'] = $request->getMethod();
            if ($request->headers->has('Origin')) {
                $_SERVER['HTTP_ORIGIN'] = (string) $request->headers->get('Origin');
            } else {
                unset($_SERVER['HTTP_ORIGIN']);
            }
            ob_start();
            carapi_handle_request();
            $body = ob_get_clean();
            $status = http_response_code();
            throw new HttpResponseException(new Response(
                is_string($body) ? $body : '',
                is_int($status) && $status >= 100 ? $status : 200,
            ));
        }
        if (! headers_sent()) {
            foreach (SecurityHeaders::headers($request) as $name => $value) {
                header($name.': '.$value);
            }
        }
        carapi_handle_request();
        exit;
    }
}
