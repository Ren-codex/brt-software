<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        
        <!-- Scripts -->
        @env('local', 'development')
            <!-- Development: Use Vite dev server -->
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <!-- Production: Use built assets -->
            @php
                // Load the manifest file
                $manifestPath = public_path('build/manifest.json');
                if (file_exists($manifestPath)) {
                    $manifest = json_decode(file_get_contents($manifestPath), true);
                }
            @endphp
            
            @if(isset($manifest) && isset($manifest['resources/js/app.js']))
                <!-- CSS -->
                @if(isset($manifest['resources/js/app.js']['css']))
                    @foreach($manifest['resources/js/app.js']['css'] as $css)
                        <link rel="stylesheet" href="{{ asset('build/' . $css) }}">
                    @endforeach
                @endif
                
                <!-- JavaScript -->
                <script type="module" src="{{ asset('build/' . $manifest['resources/js/app.js']['file']) }}"></script>
            @else
                <!-- Fallback if manifest doesn't exist -->
                <script type="module" src="{{ asset('build/assets/app.js') }}"></script>
                <link rel="stylesheet" href="{{ asset('build/assets/app.css') }}">
            @endif
        @endenv
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>