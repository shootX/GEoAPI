<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CarAPI Admin API</title>
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; font-family: "Noto Sans Georgian", system-ui, sans-serif; background: #fff; color: #111; }
        header { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; }
        header p { margin: 4px 0 0; color: #4b5563; }
        #ops { padding: 8px 24px 24px; }
        #ops li { font-family: ui-monospace, monospace; margin: 4px 0; }
        #swagger-ui { border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <header>
        <h1>CarAPI Admin API</h1>
        <p>Authorization: Bearer ტოკენი, abilities: ["*"], როლი admin. სპეციფიკაცია: <a href="{{ $specUrl }}">openapi.json</a></p>
    </header>
    <section id="ops">
        <h2>Endpoint-ები</h2>
        <ul>
            @foreach ($operations as $operation)
                <li>{{ $operation['method'] }} {{ $operation['path'] }} — {{ $operation['summary'] }}</li>
            @endforeach
        </ul>
    </section>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: @json($specUrl),
            dom_id: '#swagger-ui',
            persistAuthorization: true
        });
    </script>
</body>
</html>
