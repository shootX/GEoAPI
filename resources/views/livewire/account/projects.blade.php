@php
    $en = app()->getLocale() === 'en';
    $fmt = fn (int $n): string => number_format($n, 0, '.', ' ');
    $max = 1;
    foreach ($days as $day) {
        $max = max($max, (int) $day['used']);
    }
    $quota = $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : 0;
    $plan = ! empty($unlimited) ? 'Premium' : ((int) $limit === 2000 ? 'Basic' : ((int) $limit === 100 ? 'Free' : 'Legacy'));
    if (! empty($v2['label'])) {
        $plan = (string) $v2['label'];
    }
    $planBd = $plan === 'Legacy' ? 'bd-ne' : ($plan === 'Free' ? 'bd-out' : 'bd-ac');
    $who = (string) ($project['name'] ?? ($en ? 'Project' : 'პროექტი'));
    $me = auth()->user();
    $verified = $me === null || $me->email_verified_at !== null || ! empty($demo);
    $dayFmt = fn ($v) => $v ? substr((string) $v, 0, 10) : '—';
@endphp
<div>
<div class="app">
    <x-ga.panel-side :spa="true" active="overview" :project="$project['name'] ?? null" :plan="$plan" />
    <div class="main">
        <header class="top">
            <button class="btn menu" id="menu" type="button" aria-expanded="false" aria-controls="side" aria-label="{{ __('Menu') }}"><x-ga.icon name="menu" :size="20" /></button><a class="mlogo" href="{{ url('/') }}"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" height="22" width="69"></a>
            <h1 id="title">{{ $en ? 'Overview' : 'მიმოხილვა' }}</h1>
            <div class="who"><span class="seg hide-m" style="padding:3px"><a href="{{ route('locale.switch', 'ka') }}" class="{{ $en ? '' : 'on' }}">KA</a><a href="{{ route('locale.switch', 'en') }}" class="{{ $en ? 'on' : '' }}">EN</a></span><span class="bd {{ $planBd }}">{{ $plan }}</span></div>
        </header>
        <div class="content">
            @if ($unavailable)
                <p class="banner">{{ $en ? 'The project database is temporarily unavailable.' : 'პროექტების ბაზა დროებით მიუწვდომელია.' }}</p>
            @endif
            @unless ($verified)
                <div class="al al-wa"><x-ga.icon name="mail" :size="18" /><div><b>{{ $en ? 'Confirm your email' : 'დაადასტურეთ ელფოსტა' }}</b><p class="xs">{{ $en ? 'Secret keys stay blocked (403) until '.$me->email.' is confirmed.' : 'საიდუმლო გასაღების შექმნა დაბლოკილია (403), სანამ '.$me->email.'-ს არ დაადასტურებთ.' }}</p></div></div>
            @endunless
            <section class="screen active" id="overview">
                <div class="ph"><div><h2 class="h">{{ $en ? 'Overview' : 'მიმოხილვა' }}</h2><p class="muted">{{ $who }} · {{ $en ? 'updated' : 'განახლებულია' }} {{ now()->format('Y-m-d H:i') }}</p></div><div class="row"><button class="btn" type="button" data-go="new"><x-ga.icon name="plus" :size="16" />{{ $en ? 'New project' : 'ახალი პროექტი' }}</button><button class="btn pine" type="button" data-go="keys"><x-ga.icon name="key" :size="16" />{{ $en ? 'New key' : 'ახალი გასაღები' }}</button></div></div>
                @if (! $unavailable && $project === null)
                    <div class="panel empty"><span class="ic"><x-ga.icon name="fold" :size="22" /></span><h2>{{ $en ? 'Create your first project' : 'ჯერ შექმენით პროექტი' }}</h2><p class="muted">{{ $en ? 'A project has its own keys, limits and plan. Free — 100 requests a month.' : 'პროექტს ექნება საკუთარი გასაღებები, ლიმიტი და გეგმა. Free — 100 მოთხოვნა თვეში.' }}</p><button class="btn pine" type="button" data-go="new"><x-ga.icon name="plus" :size="16" />{{ $en ? 'New project' : 'ახალი პროექტი' }}</button></div>
                @endif
                <div class="tiles">
                    <article class="tile"><span>{{ $en ? 'Used this month' : 'გამოყენებული თვეში' }}</span><b>{{ $fmt($used) }}</b></article>
                    <article class="tile"><span>{{ $en ? 'Remaining' : 'დარჩენილი' }}</span><b>{{ $fmt($remaining) }}</b></article>
                    <article class="tile"><span>{{ $en ? 'Per minute' : 'წუთში' }}</span><b>{{ $perMinute }}</b></article>
                    <article class="tile"><span>{{ $en ? 'Active keys' : 'აქტიური გასაღები' }}</span><b>{{ $activeKeys }}</b></article>
                </div>
                <div class="g21">
                <section class="panel">
                    <h2>{{ $en ? 'Requests in the last 30 days' : 'მოთხოვნები ბოლო 30 დღეში' }}</h2>
                    <svg class="chart" viewBox="0 0 600 180" preserveAspectRatio="none" role="img" aria-label="{{ $en ? 'Requests in the last 30 days' : 'ბოლო 30 დღის მოთხოვნები' }}">
                        @foreach ([20, 70, 120] as $y)<line x1="0" x2="600" y1="{{ $y }}" y2="{{ $y }}" stroke="#E4E3EE" stroke-dasharray="3 4"></line>@endforeach
                        @foreach ($days as $index => $day)
                            @php $h = max(4, (int) round(((int) $day['used'] / $max) * 150)); @endphp
                            <rect x="{{ 3 + $index * 20 }}" y="{{ 165 - $h }}" width="14" height="{{ $h }}" rx="3"></rect>
                        @endforeach
                    </svg>
                    <p class="xs mut" style="margin-top:8px">{{ $en ? 'HTTP 200 only · widget and API share one counter' : 'მხოლოდ HTTP 200 · ვიჯეტი და API ერთ მრიცხველზე' }}</p>
                </section>
                <section class="panel">
                    <div class="row" style="justify-content:space-between;margin-bottom:14px"><h2 style="margin:0">{{ $en ? 'Plan' : 'გეგმა' }}</h2><span class="bd {{ $planBd }}">{{ $plan }}</span></div>
                    <div class="row" style="justify-content:space-between;margin-bottom:8px"><span style="font-weight:500">{{ $en ? 'Monthly quota' : 'თვიური კვოტა' }}</span><span class="mono sm"><b>{{ $fmt($used) }}</b> <span class="mut">/ {{ ! empty($unlimited) ? '∞' : $fmt($limit) }}</span></span></div>
                    <div class="quota" aria-label="{{ $fmt($used) }} / {{ $fmt($limit) }}"><span style="width: {{ ! empty($unlimited) ? 0 : $quota }}%"></span></div>
                    <p class="muted" style="margin-top:8px">@if (! empty($v2)){{ $en ? $fmt($used).' successful requests of '.$fmt($limit).'. Shared by the projects on this account.' : $fmt($used).' წარმატებული მოთხოვნა '.$fmt($limit).'-დან. ანგარიშის პროექტები ერთ კვოტას იყოფენ.' }}@elseif (! empty($unlimited)){{ $en ? $fmt($used).' successful requests. Unlimited for this project.' : $fmt($used).' წარმატებული მოთხოვნა. ამ პროექტზე შეუზღუდავია.' }}@else{{ $en ? $fmt($used).' successful requests of '.$fmt($limit).'. '.$plan.' plan, per project.' : $fmt($used).' წარმატებული მოთხოვნა '.$fmt($limit).'-დან. '.$plan.' გეგმა, პროექტზე.' }}@endif</p>
                    <div class="mini-plans">
                        @foreach (! empty($v2) ? [['Free', '1 000'], ['Starter', '25 000'], ['Growth', '150 000']] : [['Free', '100'], ['Basic', '2 000'], ['Premium', '∞']] as [$n, $q])
                            <div class="{{ $plan === $n ? 'on' : '' }}"><b>{{ $n }}</b><span class="xs mut">{{ $q }} / {{ $en ? 'mo' : 'თვე' }}</span></div>
                        @endforeach
                    </div>
                    <a class="btn" style="width:100%;justify-content:center;margin-top:14px" href="{{ route('account.billing') }}">{{ $en ? 'Change the plan' : 'გეგმის შეცვლა' }}</a>
                </section>
                </div>
            </section>

            <section class="screen" id="keys">
                @if (session('revealed_key'))
                    <p class="reveal">{{ session('revealed_key') }}</p>
                @endif
                <form class="panel row" wire:submit="createKey">
                    <label>{{ $en ? 'Name' : 'სახელი' }}<input wire:model="key_name" required maxlength="80" placeholder="{{ $en ? 'Site' : 'საიტი' }}"></label>
                    <label>{{ $en ? 'Type' : 'ტიპი' }}
                        <select wire:model="key_kind">
                            <option value="public">{{ $en ? 'Public' : 'საჯარო' }}</option>
                            <option value="secret">{{ $en ? 'Secret' : 'საიდუმლო' }}</option>
                        </select>
                    </label>
                    <button class="btn pine" type="submit">{{ $en ? 'Create' : 'შექმნა' }}</button>
                </form>
                @error('key_name') <p class="err">{{ $message }}</p> @enderror
                <div class="panel flush">
                    <div class="ph"><h2>{{ $en ? 'API keys' : 'API გასაღებები' }}</h2><span class="bd bd-out">{{ count($keys) + (is_array($widget) && ! empty($widget['public_key']) ? 1 : 0) }}</span></div>
                    <div class="tw"><table>
                        <thead><tr><th>{{ $en ? 'Name' : 'სახელი' }}</th><th>{{ $en ? 'Key' : 'გასაღები' }}</th><th>{{ $en ? 'Last used' : 'ბოლო გამოყენება' }}</th><th>{{ $en ? 'Expires' : 'ვადა' }}</th><th>{{ $en ? 'Status' : 'სტატუსი' }}</th><th></th></tr></thead>
                        <tbody>
                        @if (is_array($widget) && ! empty($widget['public_key']))
                            <tr><td><b>{{ $en ? 'Widget' : 'ვიჯეტი' }}</b><div class="xs mut">{{ $en ? 'public' : 'საჯარო' }}</div></td><td class="mono">{{ mb_substr($widget['public_key'], 0, 16) }}…</td><td>—</td><td>—</td><td><span class="bd bd-ok">{{ $en ? 'Active' : 'აქტიური' }}</span></td><td><button class="btn sm copy" type="button" data-copy="{{ $widget['public_key'] }}"><x-ga.icon name="copy" :size="14" /></button></td></tr>
                        @endif
                        @forelse ($keys as $key)
                            @php
                                $off = ($key['revoked_at'] ?? null) !== null;
                                $secret = (string) ($key['demo_value'] ?? ('geoapi_sk_'.($key['key_selector'] ?? '')));
                            @endphp
                            <tr class="{{ $off ? 'dim' : '' }}"><td><b>{{ $key['name'] }}</b><div class="xs mut">{{ $en ? 'secret' : 'საიდუმლო' }}</div></td><td class="mono">{{ mb_substr($secret, 0, 18) }}…</td><td>{{ $dayFmt($key['last_used_at'] ?? null) }}</td><td>{{ $dayFmt($key['expires_at'] ?? null) }}</td>
                                <td>@if ($off)<span class="bd bd-er">{{ $en ? 'Revoked' : 'გაუქმებული' }}</span>@else<span class="bd bd-ok">{{ $en ? 'Active' : 'აქტიური' }}</span>@endif</td>
                                <td>@unless ($off)@if (empty($demo))<button class="lnk-er" type="button" wire:click="revokeKey({{ (int) $key['id'] }})" wire:confirm="{{ $en ? 'Revoke this key?' : 'გავაუქმოთ გასაღები?' }}">{{ $en ? 'Revoke' : 'გაუქმება' }}</button>@else<span class="lnk-er">{{ $en ? 'Revoke' : 'გაუქმება' }}</span>@endif @endunless</td></tr>
                        @empty
                            @unless (is_array($widget) && ! empty($widget['public_key']))
                                <tr><td colspan="6" class="mut" style="text-align:center;height:64px">{{ $en ? 'No keys yet.' : 'გასაღები ჯერ არ არის.' }}</td></tr>
                            @endunless
                        @endforelse
                        </tbody>
                    </table></div>
                </div>
            </section>

            <section class="screen" id="plan">
                <div class="plans">
                    @foreach (! empty($v2) ? [
                        ['Free', \App\Billing\Plans::lari(0), $en ? '1 000 successful requests a month, 1 project' : '1 000 წარმატებული მოთხოვნა თვეში, 1 პროექტი'],
                        ['Starter', \App\Billing\Plans::lari(\App\Billing\V2\Catalog::tetri('starter', 'month')), $en ? '25 000 successful requests a month, 3 projects' : '25 000 წარმატებული მოთხოვნა თვეში, 3 პროექტი'],
                        ['Growth', \App\Billing\Plans::lari(\App\Billing\V2\Catalog::tetri('growth', 'month')), $en ? '150 000 successful requests a month, 10 projects' : '150 000 წარმატებული მოთხოვნა თვეში, 10 პროექტი'],
                    ] : [
                        ['Free', \App\Billing\Plans::lari(0), $en ? '100 successful requests a month' : '100 წარმატებული მოთხოვნა თვეში'],
                        ['Basic', \App\Billing\Plans::lari(\App\Billing\Plans::tetri('basic', 'month')), $en ? '2 000 successful requests a month' : '2 000 წარმატებული მოთხოვნა თვეში'],
                        ['Premium', \App\Billing\Plans::lari(\App\Billing\Plans::tetri('premium', 'month')), $en ? 'Unlimited successful requests' : 'შეუზღუდავი წარმატებული მოთხოვნა'],
                    ] as [$name, $price, $quotaText])
                        <article class="plan {{ $plan === $name ? 'now' : '' }}">
                            <div class="row" style="justify-content:space-between"><h2>{{ $name }}</h2>@if ($plan === $name)<span class="bd bd-ac">{{ $en ? 'Current' : 'მიმდინარე' }}</span>@endif</div>
                            <p class="price">{{ $price }} <span class="muted">/ {{ $en ? 'month' : 'თვე' }}</span></p>
                            <ul>
                                <li>{{ $quotaText }}</li>
                                <li>{{ $perMinute }} {{ $en ? 'requests a minute' : 'მოთხოვნა წუთში' }}</li>
                                <li>{{ $en ? 'Server API and widget' : 'სერვერული API და ვიჯეტი' }}</li>
                            </ul>
                        </article>
                    @endforeach
                </div>
                <div class="panel row" style="justify-content:space-between">
                    <p class="muted">@if (! empty($v2)){{ $en ? 'One subscription covers the workspace. Prices include VAT. A year is 15% less and does not renew by itself.' : 'ერთი გამოწერა ფარავს სამუშაო სივრცეს. ფასი დღგ-ს შეიცავს. წელიწადი 15%-ით ნაკლებია და თავისით არ გრძელდება.' }}@else{{ $en ? 'The plan applies to this project. Prices include VAT; a year is 12% cheaper.' : 'გეგმა მოქმედებს ამ პროექტზე. ფასი დღგ-ს შეიცავს; წლიური 12%-ით იაფია.' }}@endif</p>
                    <a class="btn pine" href="{{ route('account.billing') }}">{{ $en ? 'Change the plan' : 'გეგმის შეცვლა' }}</a>
                </div>
            </section>

            <section class="screen" id="projects">
                <div class="ph"><div><h2 class="h">{{ $en ? 'Projects' : 'პროექტები' }}</h2><p class="muted">@if (! empty($v2)){{ $en ? 'Active projects' : 'აქტიური პროექტები' }}: {{ (int) $v2['active'] }} / {{ (int) $v2['allowed'] }}. {{ $en ? 'Usage is shared. A cap is optional.' : 'მოხმარება საერთოა. ზღვარი არასავალდებულოა.' }}@else{{ $en ? 'Each project has its own keys, limits and plan' : 'თითოეულ პროექტს აქვს საკუთარი გასაღებები, ლიმიტი და გეგმა' }}@endif</p></div><button class="btn pine" type="button" data-go="new"><x-ga.icon name="plus" :size="16" />{{ $en ? 'New project' : 'ახალი პროექტი' }}</button></div>
                @if (! empty($v2))
                    <form class="panel row" wire:submit="setCap" data-v2-cap>
                        <label>{{ $en ? 'Cap for the selected project' : 'არჩეული პროექტის ზღვარი' }}<input wire:model="capInput" inputmode="numeric" placeholder="{{ $en ? 'empty clears it' : 'ცარიელი ხსნის' }}"></label>
                        <button class="btn" type="submit">{{ $en ? 'Save cap' : 'ზღვრის შენახვა' }}</button>
                        @error('capInput') <p class="err">{{ $message }}</p> @enderror
                    </form>
                @endif
                <div class="panel flush"><div class="ph"><label class="srch"><x-ga.icon name="search" :size="16" /><input type="search" id="proj-q" placeholder="{{ $en ? 'Search' : 'ძიება' }}" aria-label="{{ $en ? 'Search projects' : 'პროექტების ძიება' }}" autocomplete="off"></label></div><div class="tw"><table id="proj-t">
                    <thead><tr><th>{{ $en ? 'Project' : 'პროექტი' }}</th><th>{{ $en ? 'This month' : 'ამ თვის მოხმარება' }}</th><th>{{ $en ? 'Status' : 'სტატუსი' }}</th><th>{{ $en ? 'Created' : 'შექმნა' }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($projects as $row)
                        @php
                            $rowName = (string) ($row['name'] ?? ''); $on = ($row['status'] ?? 'active') === 'active';
                            $u = $usageById[(int) $row['id']] ?? null;
                            $uPct = $u && ! $u['unlimited'] && $u['limit'] > 0 ? min(100, round($u['used'] / $u['limit'] * 100)) : 0;
                        @endphp
                        <tr data-name="{{ mb_strtolower($rowName.' '.($row['website_url'] ?? '')) }}"><td><div class="row" style="flex-wrap:nowrap"><span class="av">{{ mb_strtoupper(mb_substr($rowName, 0, 1)) }}</span><div style="min-width:0"><b>{{ $rowName }}</b>@if ((int) $row['id'] === (int) ($project['id'] ?? 0)) <span class="bd bd-ac">{{ $en ? 'selected' : 'არჩეული' }}</span>@endif<div class="xs mut mono">{{ $row['website_url'] ?? '' ?: '—' }}</div></div></div></td>
                            <td>@if ($u)<div class="ubar"><div class="quota"><span style="width:{{ $uPct }}%;{{ $uPct >= 90 ? 'background:#B3261E' : '' }}"></span></div><span class="mono xs">{{ $fmt((int) $u['used']) }} / {{ $u['unlimited'] ? '∞' : $fmt((int) $u['limit']) }}</span></div>@else<span class="mut">—</span>@endif</td>
                            <td>@if ($on)<span class="bd bd-ok">{{ $en ? 'Active' : 'აქტიური' }}</span>@else<span class="bd bd-out">{{ $en ? 'Disabled' : 'გამორთული' }}</span>@endif</td>
                            <td class="mono xs">{{ $dayFmt($row['created_at'] ?? null) }}</td>
                            <td style="text-align:right;white-space:nowrap">@if (empty($demo))<button class="btn sm" type="button" wire:click="selectProject({{ (int) $row['id'] }})" data-go="overview">{{ $en ? 'Select' : 'არჩევა' }}</button> <a class="btn sm" href="{{ url('/account/projects/'.(int) $row['id']) }}">{{ $en ? 'Open' : 'გახსნა' }} <x-ga.icon name="chev" :size="14" /></a>@endif</td></tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><span class="ic"><x-ga.icon name="fold" :size="22" /></span><h2>{{ $en ? 'No projects yet' : 'პროექტი ჯერ არ გაქვთ' }}</h2><button class="btn pine" type="button" data-go="new"><x-ga.icon name="plus" :size="16" />{{ $en ? 'New project' : 'ახალი პროექტი' }}</button></div></td></tr>
                    @endforelse
                    <tr id="proj-none" hidden><td colspan="5" class="mut" style="text-align:center;height:64px">{{ $en ? 'Nothing found.' : 'ვერაფერი მოიძებნა.' }}</td></tr>
                    </tbody>
                </table></div></div>
            </section>

            <section class="screen" id="new">
                <div><h2 class="h">{{ $en ? 'New project' : 'ახალი პროექტი' }}</h2><p class="muted">@if (! empty($v2)){{ $en ? 'A new project shares the workspace quota. It does not reset usage.' : 'ახალი პროექტი სამუშაო სივრცის კვოტას იყოფს. მოხმარებას არ ანულებს.' }}@else{{ $en ? 'A project starts on Free — 100 successful requests a month.' : 'პროექტი იწყება Free გეგმით — 100 წარმატებული მოთხოვნა თვეში.' }}@endif</p></div>
                <form class="panel form-n" wire:submit="create">
                    <label>{{ $en ? 'Project name' : 'პროექტის სახელი' }}<input wire:model="name" required minlength="2" maxlength="120" placeholder="autopass-web"></label>
                    @error('name') <p class="err">{{ $message }}</p> @enderror
                    <label>{{ $en ? 'Website' : 'საიტის მისამართი' }} <span class="xs mut">{{ $en ? 'optional' : 'არასავალდებულო' }}</span><input wire:model="website_url" class="mono" placeholder="https://example.com"></label>
                    @error('website_url') <p class="err">{{ $message }}</p> @enderror
                    <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-go="projects">{{ $en ? 'Cancel' : 'გაუქმება' }}</button><button class="btn pine" type="submit" @if (! empty($demo)) disabled @endif><span wire:loading.remove wire:target="create">{{ $en ? 'Create project' : 'პროექტის შექმნა' }}</span><span wire:loading wire:target="create">…</span></button></div>
                </form>
            </section>

            <section class="screen" id="usage">
                <div class="tiles">
                    <article class="tile"><span>{{ $en ? 'This month' : 'ამ თვეში' }}</span><b>{{ $fmt($used) }}</b></article>
                    <article class="tile"><span>{{ $en ? 'Limit' : 'ლიმიტი' }}</span><b>{{ ! empty($unlimited) ? '∞' : $fmt($limit) }}</b></article>
                    <article class="tile"><span>{{ $en ? 'Remaining' : 'დარჩენილი' }}</span><b>{{ ! empty($unlimited) ? '∞' : $fmt($remaining) }}</b></article>
                    <article class="tile"><span>{{ $en ? 'Per minute' : 'წუთში' }}</span><b>{{ $perMinute }}</b></article>
                </div>
                <section class="panel">
                    <h2>{{ $en ? 'Successful requests, last 30 UTC days' : 'წარმატებული მოთხოვნები, ბოლო 30 UTC დღე' }}</h2>
                    <svg class="chart" viewBox="0 0 600 180" preserveAspectRatio="none" role="img" aria-label="30 days">
                        @foreach ([20, 70, 120] as $y)<line x1="0" x2="600" y1="{{ $y }}" y2="{{ $y }}" stroke="#E4E3EE" stroke-dasharray="3 4"></line>@endforeach
                        @foreach ($days as $index => $day)
                            @php $h = max(4, (int) round(((int) $day['used'] / $max) * 150)); @endphp
                            <rect x="{{ 3 + $index * 20 }}" y="{{ 165 - $h }}" width="14" height="{{ $h }}" rx="3"><title>{{ $day['day'] }}: {{ $day['used'] }}</title></rect>
                        @endforeach
                    </svg>
                    <p class="xs mut" style="margin-top:8px">{{ $en ? 'HTTP 200 only · widget and API share one counter · resets on the 1st (UTC)' : 'მხოლოდ HTTP 200 · ვიჯეტი და API ერთ მრიცხველზე · ნულდება თვის 1-ს (UTC)' }}</p>
                </section>
                <div class="panel flush"><div class="ph"><h2>{{ $en ? 'By day' : 'დღეების მიხედვით' }}</h2></div><div class="tw" style="max-height:360px;overflow:auto"><table>
                    <thead><tr><th>{{ $en ? 'Day (UTC)' : 'დღე (UTC)' }}</th><th style="text-align:right">{{ $en ? 'Requests' : 'მოთხოვნები' }}</th><th style="width:45%"></th></tr></thead>
                    <tbody>@foreach (array_reverse($days) as $day)<tr><td class="mono xs">{{ $day['day'] ?: '—' }}</td><td class="mono" style="text-align:right">{{ $fmt((int) $day['used']) }}</td><td><div class="quota"><span style="width:{{ round(((int) $day['used'] / $max) * 100) }}%"></span></div></td></tr>@endforeach</tbody>
                </table></div></div>
            </section>

            <section class="screen" id="settings">
                <div class="set-row"><div><h2>{{ $en ? 'Profile' : 'პროფილი' }}</h2><p class="xs mut">{{ $en ? 'Name and email' : 'სახელი და ელფოსტა' }}</p></div>
                    <div class="panel form-n">
                        @php $editUrl = \Illuminate\Support\Facades\Route::has('settings.profile') ? route('settings.profile') : null; $editTxt = $en ? 'Edit' : 'რედაქტირება'; @endphp
                        <label><span class="lrow"><span>{{ $en ? 'Name' : 'სახელი' }}</span>@if ($editUrl)<a class="edit" href="{{ $editUrl }}">{{ $editTxt }}</a>@endif</span><input value="{{ $me?->name }}" readonly></label>
                        <label><span class="lrow"><span>{{ $en ? 'Email' : 'ელფოსტა' }} @if ($verified)<span class="bd bd-ok">{{ $en ? 'verified' : 'დადასტურებული' }}</span>@else<span class="bd bd-wa">{{ $en ? 'not verified' : 'დაუდასტურებელი' }}</span>@endif</span>@if ($editUrl)<a class="edit" href="{{ $editUrl }}">{{ $editTxt }}</a>@endif</span><input value="{{ $me?->email }}" readonly></label>
                    </div></div>
                <div class="set-row"><div><h2>{{ $en ? 'Security' : 'უსაფრთხოება' }}</h2><p class="xs mut">{{ $en ? 'Password, 2FA, sessions' : 'პაროლი, 2FA, სესიები' }}</p></div>
                    <div class="panel row" style="justify-content:space-between"><div class="row"><span class="ic"><x-ga.icon name="lock" :size="18" /></span><div><b>{{ $en ? 'Password and two-factor' : 'პაროლი და ორსაფეხურიანი ავთენტიფიკაცია' }}</b><p class="xs mut">{{ $en ? 'Password at least 15 characters · TOTP app' : 'პაროლი მინიმუმ 15 სიმბოლო · TOTP აპი' }}</p></div></div>
                        @if (\Illuminate\Support\Facades\Route::has('settings.security'))<a class="edit" href="{{ route('settings.security') }}">{{ $en ? 'Edit' : 'რედაქტირება' }}</a>@endif</div></div>
                <form method="post" action="{{ url('/logout') }}" class="row" style="justify-content:flex-end">@csrf<button class="btn" type="submit">{{ $en ? 'Log out' : 'გასვლა' }}</button></form>
            </section>

            <section class="screen" id="catalog">
                <div class="grid" id="catalog-grid">
                    @foreach ($cars as $car)
                        <button class="card" type="button" data-car="{{ json_encode($car, JSON_UNESCAPED_UNICODE) }}">
                            <div class="shot"><svg viewBox="0 0 160 72" fill="none" stroke="#4338CA" stroke-width="2.2"><path d="M14 46h132l-10-18a8 8 0 0 0-7-4H34a8 8 0 0 0-7 4L14 46z"/><path d="M28 46v6M132 46v6"/><circle cx="42" cy="52" r="7" fill="#A5A0F0"/><circle cx="118" cy="52" r="7" fill="#A5A0F0"/></svg><span class="watermark">GEOAPI.GE</span></div>
                            <div class="body"><strong>{{ $car['brand'] }} {{ $car['model'] }}</strong><p class="muted">{{ $car['years'] }} · {{ $car['body'] }}</p></div>
                        </button>
                    @endforeach
                </div>
                <p class="muted" id="catalog-note" hidden></p>
            </section>
        </div>
    </div>
</div>
<div class="drawer-back" id="drawer-back"></div>
<aside class="drawer" id="drawer" aria-hidden="true">
    <div class="shot" id="drawer-shot"></div>
    <div class="pad">
        <h2 id="drawer-title"></h2>
        <dl class="specs" id="drawer-specs"></dl>
        <p class="url" id="drawer-url"></p>
        <pre id="drawer-json"></pre>
        <button class="btn" id="drawer-close" type="button">{{ $en ? 'Close' : 'დახურვა' }}</button>
    </div>
</aside>
<script @nonce>
    (function () {
        const titles = { overview: @json($en ? 'Overview' : 'მიმოხილვა'), projects: @json($en ? 'Projects' : 'პროექტები'), new: @json($en ? 'New project' : 'ახალი პროექტი'), usage: @json($en ? 'Usage' : 'მოხმარება'), keys: @json($en ? 'API keys' : 'API გასაღებები'), plan: @json($en ? 'Plan' : 'გეგმა'), catalog: @json($en ? 'Catalog' : 'კატალოგი'), settings: @json($en ? 'Settings' : 'პარამეტრები') };
        var current = 'overview';
        const side = document.getElementById('side');
        const menu = document.getElementById('menu');
        function show(name) {
            if (!document.getElementById(name)) return;
            current = name;
            document.querySelectorAll('.nav button').forEach(function (item) { item.toggleAttribute('aria-current', item.dataset.screen === (name === 'new' ? 'projects' : name)); if (item.hasAttribute('aria-current')) item.setAttribute('aria-current', 'page'); });
            document.querySelectorAll('.screen').forEach(function (screen) { screen.classList.toggle('active', screen.id === name); });
            document.getElementById('title').textContent = titles[name] || '';
            side.classList.remove('open');
            menu.setAttribute('aria-expanded', 'false');
        }
        document.addEventListener('click', function (event) {
            var el = event.target.closest('.nav button[data-screen], [data-go]');
            if (!el) return;
            show(el.dataset.screen || el.dataset.go);
            if (history.replaceState) history.replaceState(null, '', '#' + current);
        });
        var hooked = false;
        function hook() {
            if (hooked || !window.Livewire || !Livewire.hook) return;
            hooked = true;
            Livewire.hook('commit', function (c) { c.succeed(function () { queueMicrotask(function () { show(current); }); }); });
            Livewire.on('screen', function (e) { var n = (e && e.name) || (Array.isArray(e) && e[0] && e[0].name); if (n) current = n; });
        }
        document.addEventListener('livewire:init', hook);
        hook();
        document.addEventListener('input', function (event) {
            if (event.target.id !== 'proj-q') return;
            var q = event.target.value.trim().toLowerCase(), shown = 0;
            document.querySelectorAll('#proj-t tbody tr[data-name]').forEach(function (tr) { var ok = !q || tr.dataset.name.indexOf(q) !== -1; tr.hidden = !ok; if (ok) shown++; });
            var none = document.getElementById('proj-none'); if (none) none.hidden = shown !== 0 || !q;
        });
        var initial = (location.hash || '').replace('#', '');
        if (initial) show(initial);
        window.addEventListener('hashchange', function () { show((location.hash || '').replace('#', '') || 'overview'); });
        menu.addEventListener('click', function () {
            const open = side.classList.toggle('open');
            menu.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.querySelectorAll('.copy').forEach(function (button) {
            button.addEventListener('click', function () {
                const value = button.getAttribute('data-copy') || '';
                if (navigator.clipboard) navigator.clipboard.writeText(value);
                button.textContent = @json($en ? 'Copied' : 'დაკოპირდა');
            });
        });
        const car = '<svg viewBox="0 0 160 72" fill="none" stroke="#4338CA" stroke-width="2.2"><path d="M14 46h132l-10-18a8 8 0 0 0-7-4H34a8 8 0 0 0-7 4L14 46z"/><path d="M28 46v6M132 46v6"/><circle cx="42" cy="52" r="7" fill="#A5A0F0"/><circle cx="118" cy="52" r="7" fill="#A5A0F0"/></svg><span class="watermark">GEOAPI.GE</span>';
        const grid = document.getElementById('catalog-grid');
        const note = document.getElementById('catalog-note');
        const drawer = document.getElementById('drawer');
        const back = document.getElementById('drawer-back');
        function closeDrawer() {
            drawer.classList.remove('open');
            back.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
        }
        document.getElementById('drawer-close').addEventListener('click', closeDrawer);
        back.addEventListener('click', closeDrawer);
        function openDrawer(item) {
            document.getElementById('drawer-shot').innerHTML = car;
            document.getElementById('drawer-title').textContent = item.brand + ' ' + item.model;
            const specs = [
                ['{{ $en ? 'Brand' : 'მარკა' }}', item.brand],
                ['{{ $en ? 'Model' : 'მოდელი' }}', item.model],
                ['{{ $en ? 'Years' : 'წლები' }}', item.years || ''],
                ['{{ $en ? 'Body' : 'ძარა' }}', item.body || ''],
                ['{{ $en ? 'Engine' : 'ძრავი' }}', item.engine || ''],
                ['{{ $en ? 'Power' : 'სიმძლავრე' }}', item.power || '']
            ].filter(function (pair) { return pair[1] !== ''; });
            const list = document.getElementById('drawer-specs');
            list.innerHTML = '';
            specs.forEach(function (pair) {
                const wrap = document.createElement('div');
                wrap.innerHTML = '<dt></dt><dd></dd>';
                wrap.querySelector('dt').textContent = pair[0];
                wrap.querySelector('dd').textContent = pair[1];
                list.appendChild(wrap);
            });
            const slug = item.slug || item.brandId;
            const url = slug ? 'GET /api/v1/brands/' + slug + '/models' : '';
            document.getElementById('drawer-url').textContent = url;
            const modelId = item.id || item.modelId;
            const modelSlug = String(item.model || '').toLowerCase().replace(/\s+/g, '-');
            document.getElementById('drawer-json').textContent = JSON.stringify({
                data: [{ id: String(modelId), slug: modelSlug, name: item.model }],
                meta: { page: 1, per_page: 50, total: 1 }
            }, null, 2);
            drawer.classList.add('open');
            back.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
        }
        grid.querySelectorAll('[data-car]').forEach(function (button) {
            button.addEventListener('click', function () {
                openDrawer(JSON.parse(button.getAttribute('data-car')));
            });
        });
        if (grid.children.length) return;
        fetch('/api/picker/brands?page=1&per_page=8', { headers: { Accept: 'application/json' } })
            .then(function (response) { if (!response.ok) throw new Error('fail'); return response.json(); })
            .then(function (payload) {
                const brands = (payload.data || []).slice(0, 4);
                return Promise.all(brands.map(function (brand) {
                    return fetch('/api/picker/brands/' + encodeURIComponent(brand.id) + '/models?page=1&per_page=2', { headers: { Accept: 'application/json' } })
                        .then(function (response) { return response.ok ? response.json() : { data: [] }; })
                        .then(function (models) {
                            return (models.data || []).slice(0, 2).map(function (model) {
                                return { brand: brand.name, brandId: brand.id, model: model.name, modelId: model.id };
                            });
                        });
                }));
            })
            .then(function (groups) {
                groups.flat().slice(0, 6).forEach(function (item) {
                    const button = document.createElement('button');
                    button.className = 'card';
                    button.type = 'button';
                    button.innerHTML = '<div class="shot">' + car + '</div><div class="body"><strong></strong><p class="muted"></p></div>';
                    button.querySelector('strong').textContent = item.brand + ' ' + item.model;
                    button.querySelector('p').textContent = item.brand;
                    button.addEventListener('click', function () { openDrawer(item); });
                    grid.appendChild(button);
                });
            })
            .catch(function () {
                note.hidden = false;
                note.textContent = @json(__('Catalog is temporarily unavailable'));
            });
    })();
</script>
</div>
