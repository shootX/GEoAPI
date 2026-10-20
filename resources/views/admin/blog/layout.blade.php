@php
    $user = auth()->user();
    $name = $user?->name ?: $user?->email;
@endphp
<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $heading }} — GeoAPI ადმინი</title>
    <link rel="icon" href="/assets/brand/favicon-32-lime.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/fonts/fonts.css?v=20261018">
    <link rel="stylesheet" href="/assets/admin.css?v=20261020r">
</head>
<body class="admin-app">
    <a class="admin-skip" href="#admin-content">შინაარსზე გადასვლა</a>
    <header class="admin-bar">
        <button class="admin-menu" type="button" aria-expanded="false" aria-controls="admin-nav">მენიუ</button>
        <p class="admin-brand">GeoAPI <span>ადმინი</span></p>
        <h1>{{ $heading }}</h1>
        <div class="admin-who">
            <p><b>{{ $name }}</b><span>{{ $user?->email }}</span></p>
            <a href="/account">დეველოპერის კაბინეტი</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">გასვლა</button></form>
        </div>
    </header>
    <div class="admin-shell">
        <aside class="admin-side" id="admin-nav">
            <nav aria-label="ადმინისტრირება">
                <a href="/admin">მიმოხილვა</a>
                <a href="/admin#photo-reports">არასწორი ფოტოები</a>
                <a href="/admin/users">მომხმარებლები</a>
                <a href="/admin/projects">პროექტები</a>
                <a href="/admin/blog" aria-current="page">ბლოგი</a>
            </nav>
        </aside>
        <main class="admin-main" id="admin-content">
            {{ $slot }}
        </main>
    </div>
    <script>
        (function () {
            var button = document.querySelector('.admin-menu');
            var nav = document.getElementById('admin-nav');
            if (!button || !nav) return;
            button.addEventListener('click', function () {
                var open = !nav.classList.contains('is-open');
                nav.classList.toggle('is-open', open);
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        })();
    </script>
</body>
</html>
