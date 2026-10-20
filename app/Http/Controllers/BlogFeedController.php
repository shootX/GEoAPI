<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response;

final class BlogFeedController extends Controller
{
    public function __invoke(?string $locale = null): Response
    {
        if ($locale === 'ka' || $locale === 'en') {
            app()->setLocale($locale);
        } else {
            $locale = app()->getLocale() === 'en' ? 'en' : 'ka';
        }
        $posts = BlogPost::query()->visible()->translated($locale)->orderByDesc('publish_at')->orderByDesc('id')->limit(20)->get();
        $channel = $locale === 'en' ? 'CarAPI blog' : 'CarAPI ბლოგი';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0"><channel>';
        $xml .= '<title>'.e($channel).'</title>';
        $xml .= '<link>'.e(url('/'.$locale.'/blog')).'</link>';
        $xml .= '<description>'.e($channel).'</description>';
        foreach ($posts as $post) {
            $text = $post->translation($locale);
            $when = $post->publish_at ?? $post->created_at;
            $xml .= '<item>';
            $xml .= '<title>'.e($text['title']).'</title>';
            $xml .= '<link>'.e($post->publicUrl($locale)).'</link>';
            $xml .= '<guid>'.e($post->publicUrl($locale)).'</guid>';
            $xml .= '<pubDate>'.e($when->toRfc2822String()).'</pubDate>';
            $xml .= '<description>'.e($text['excerpt'] !== '' ? $text['excerpt'] : $text['title']).'</description>';
            $xml .= '</item>';
        }
        $xml .= '</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
