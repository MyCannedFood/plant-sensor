<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? 'Verdant — Greenhouse Monitor' }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <header class="topbar">
            <div class="topbar-inner">
                <a href="{{ route('overview') }}" class="brand">
                    <span class="brand-mark" aria-hidden="true"></span>
                    <span class="brand-titles">
                        <span class="brand-title">Verdant</span>
                        <span class="brand-sub">Greenhouse environment monitor</span>
                    </span>
                </a>
                <div class="topbar-right">
                    <span class="live-badge"><span class="dot"></span>Live</span>
                    <span class="clock mono" id="clock">--:--:--</span>
                    <x-alert-bell />
                </div>
            </div>
        </header>

        <main class="page">
            {{ $slot }}
        </main>

        <footer class="note">Verdant · sensor data reported by connected devices</footer>
    </body>
</html>