<!DOCTYPE html>
<html lang="pt-MZ">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $currentTenant?->name ?? 'EduGest' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>:root { --color-primary: {{ $currentTenant?->color('primary_color') ?? config('tenancy.primary_color') }}; --color-secondary: {{ $currentTenant?->color('secondary_color') ?? config('tenancy.secondary_color') }}; }</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    @yield('content')
</body>
</html>
