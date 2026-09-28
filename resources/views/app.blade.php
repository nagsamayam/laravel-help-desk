<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enterprise Help Desk</title>
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="h-full antialiased font-sans bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
    <div id="root" class="min-h-full"></div>
</body>
</html>
