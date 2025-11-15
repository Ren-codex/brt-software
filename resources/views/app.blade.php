<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>BRT Accounting Software</title>
        <meta name="description" content="BRT Accountitng Software">
        <meta name="keywords" content="BRT Accountitng Software">
        <meta name="author" content="Krad Jumli">
        <meta property="og:title" content="STLIMS - Science & Technology Laboratory Information Management System">
        <link rel="shortcut icon" href="{{ URL::asset('images/logo-sm.png') }}">
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>