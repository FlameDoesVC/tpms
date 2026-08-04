<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- So the password strength indicator states the rule the server
             actually enforces, rather than a hardcoded guess at it. --}}
        <meta name="password-min-length" content="{{ config('security.password.min_length') }}">
        <meta name="password-max-length" content="{{ config('security.password.max_length') }}">

        <title>{{ config('app.name', 'TPMS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Prevent flash of wrong theme -->
        <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function() {
                var t = localStorage.getItem('theme');
                if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', t);
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div id="app"></div>
    </body>
</html>
