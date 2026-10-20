<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $paths = ['/', '/guide', '/demo', '/pricing', '/privacy', '/terms', '/blog'];
        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($paths as $path) {
            $body .= '  <url><loc>'.e(url($path)).'</loc></url>'."\n";
        }
        if (Schema::hasTable('blog_posts')) {
            BlogPost::query()->visible()->orderBy('id')->each(function (BlogPost $post) use (&$body): void {
                foreach (['ka', 'en'] as $locale) {
                    if ($post->hasLocale($locale)) {
                        $body .= '  <url><loc>'.e($post->publicUrl($locale)).'</loc></url>'."\n";
                    }
                }
            });
        }
        $body .= '</urlset>';

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
