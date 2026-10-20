<?php

declare(strict_types=1);

namespace Carapi;

final class SiteChrome
{
    public static function assets(): string
    {
        return '<link rel="stylesheet" href="/assets/fonts/fonts.css?v=20261018">'
            . '<link rel="stylesheet" href="/assets/site.css?v=20261020r">';
    }

    public static function logo(string $href = '/'): string
    {
        return '<a href="' . $href . '" aria-label="carapi მთავარი">'
            . '<img class="lock lock-dark" src="/assets/brand/carapi-lockup-dark.svg" alt="carapi" height="36">'
            . '<img class="lock lock-light" src="/assets/brand/carapi-lockup-light.svg" alt="" height="36">'
            . '</a>';
    }

    public static function header(?string $active = null): string
    {
        $open = \Carapi\Platform\Http::enabled();
        $account = $open ? \Carapi\Platform\Http::accountLink() : null;
        $links = [
            'demo' => ['/#demo', 'დემო'],
            'guide' => ['/guide', 'დოკუმენტაცია'],
        ];
        $nav = '';
        foreach ($links as $key => [$href, $label]) {
            $current = $active === $key ? ' aria-current="page"' : '';
            $nav .= '<a href="' . $href . '"' . $current . '>' . $label . '</a>';
        }
        if ($account !== null) {
            $actions = '<a class="btn btn-t hide-m" href="/account">კაბინეტი</a><a class="btn btn-l" href="/account">ჩემი პროექტები</a>';
        } elseif ($open) {
            $actions = '<a class="btn btn-t hide-m" href="/login">შესვლა</a><a class="btn btn-l" href="/register">ანგარიშის შექმნა</a>';
        } else {
            $actions = '<span class="btn btn-g" id="closed">მალე გაიხსნება</span>';
        }

        return '<a class="skip" href="#content">შინაარსზე გადასვლა</a>'
            . '<header class="wrap nav" id="site-header">' . self::logo()
            . '<nav class="links" id="site-nav">' . $nav . '</nav>'
            . '<div class="act">' . self::themeButton() . $actions
            . '<button class="burger" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="მენიუ">'
            . self::icon('menu') . '</button></div></header>';
    }

    public static function footer(): string
    {
        $open = \Carapi\Platform\Http::enabled();
        $account = $open
            ? '<a href="/login">შესვლა</a><a href="/register">რეგისტრაცია</a>'
            : '';

        return '<footer class="site-foot"><div class="wrap row">'
            . '<span>© ' . date('Y') . ' შპს „ინოვა ლაბ“ · <a href="mailto:info@socialsave.cc">info@socialsave.cc</a></span>'
            . '<div class="links"><a href="/guide">დოკუმენტაცია</a>' . $account
            . '<a href="/terms">გამოყენების წესები</a><a href="/privacy">კონფიდენციალურობა</a></div>'
            . '</div></footer><script src="/assets/site.js?v=20261018f"></script>';
    }

    public static function start(string $title, ?string $description = null, bool $exactTitle = false): string
    {
        $safe = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pageTitle = $exactTitle ? $safe : $safe . ' — carapi';
        $summary = $description ?? 'CarAPI დეველოპერებს სთავაზობს ავტომობილების ბრენდების, მოდელებისა და თაობების მონაცემებს REST API-ის საშუალებით და ავტომობილის არჩევის მზა ვიჯეტს ვებსაიტებისთვის.';

        return '<!doctype html><html lang="ka"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<script>(function(){try{if(localStorage.getItem("carapi-theme")==="light")document.documentElement.dataset.theme="light"}catch(e){}})()</script>'
            . '<title>' . $pageTitle . '</title>'
            . '<meta name="description" content="' . htmlspecialchars($summary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . '<link rel="icon" href="/assets/brand/favicon-32-lime.svg" type="image/svg+xml">'
            . self::assets() . '</head><body>';
    }

    public static function end(): string
    {
        return '</body></html>';
    }

    public static function themeButton(): string
    {
        return '<button class="btn btn-g" type="button" data-theme-toggle aria-label="ღია და მუქი რეჟიმის შეცვლა" style="width:40px;padding:0">'
            . self::icon('sun') . '</button>';
    }

    public static function icon(string $name): string
    {
        $paths = match ($name) {
            'menu' => '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
            'chev' => '<path d="m6 9 6 6 6-6"/>',
            'arrow' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
            'copy' => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
            'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
            'eye' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
            'search' => '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
            'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
            default => '',
        };

        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    }
}
