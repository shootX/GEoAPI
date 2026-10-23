<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use Geoapi\Catalog\PublicCounts;
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
            'modifications' => $ok && isset($counts['modifications']) ? number_format((int) $counts['modifications'], 0, '.', ' ') : null,
            'photos' => $ok && isset($counts['photos']) ? number_format((int) $counts['photos'], 0, '.', ' ') : null,
            'specifications' => $ok && isset($counts['specifications']) ? number_format((int) $counts['specifications'], 0, '.', ' ') : null,
            'markets' => $ok && isset($counts['markets']) ? number_format((int) $counts['markets'], 0, '.', ' ') : null,
            'equipmentMillions' => $ok && isset($counts['equipment']) && (int) $counts['equipment'] >= 1000000 ? intdiv((int) $counts['equipment'], 1000000) : null,
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

    public function reference(): View
    {
        return view('public.api-docs');
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
