<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->title }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { margin: 0; padding: 1.25rem 1.5rem; font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; color: #1f2937; }
        h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 1rem; }
        h2 { font-size: 1.25rem; font-weight: 600; margin: 1.5rem 0 0.75rem; }
        h3 { font-size: 1.1rem; font-weight: 600; margin: 1.25rem 0 0.5rem; }
        p { margin: 0 0 0.75rem; line-height: 1.6; }
        ul, ol { margin: 0 0 0.75rem 1.25rem; line-height: 1.6; }
        a { color: #2563eb; text-decoration: underline; }
        .content img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <h1>{{ $page->title }}</h1>
    <div class="content">
        {!! $page->content !!}
    </div>
</body>
</html>
