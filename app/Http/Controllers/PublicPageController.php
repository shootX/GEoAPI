<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use Carapi\Catalog\PublicCounts;
use Illuminate\Contracts\View\View;

final class PublicPageController extends Controller
{
    public function home(): View
    {
        if ($page = Page::published('home')) {
            return view('public.cms', ['page' => $page]);
        }

        $counts = PublicCounts::read();
        $ok = $counts['status'] === 'ok';

        return view('public.home', [
            'brands' => $ok ? number_format((int) $counts['brands'], 0, '.', ' ') : null,
            'models' => $ok ? number_format((int) $counts['models'], 0, '.', ' ') : null,
            'generations' => $ok ? number_format((int) $counts['generations'], 0, '.', ' ') : null,
            'generationGroups' => $ok ? number_format((int) ($counts['generation_groups'] ?? 0), 0, '.', ' ') : null,
            'catalogUp' => $ok,
            'measuredAt' => $ok ? now()->toDateString() : null,
        ]);
    }

    public function demo(): View
    {
        return $this->page('demo', 'public.demo');
    }

    public function guide(): View
    {
        return view('public.guide');
    }

    public function privacy(): View
    {
        return $this->page('privacy', 'public.privacy');
    }

    public function terms(): View
    {
        return $this->page('terms', 'public.terms');
    }

    private function page(string $slug, string $view): View
    {
        $page = Page::published($slug);

        return $page === null ? view($view) : view('public.cms', ['page' => $page]);
    }
}
