<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">
    <nav class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <a href="{{ route('home') }}" class="text-lg font-bold text-blue-600">
            JobBoard
        </a>
    
        <div class="flex items-center gap-4">
            @guest
                <a href="{{ route('register') }}" class="text-sm text-gray-600 hover:text-blue-600">For Employers</a>
                <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-blue-600">Log in</a>
                <a href="{{ route('register') }}" class="text-sm bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Sign
                    up</a>
            @endguest
        
            @auth
                <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-blue-600">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-600 hover:text-blue-600">Log out</button>
                </form>
            @endauth
        </div>
    </nav>

    {{-- Phase 4: session flash messages (e.g. the job-listing / account
    deletion guards) were being set via redirect()->with('error'/'success', ...)
    but nothing in this shared guest layout ever rendered them -- so a blocked
    action silently redirected back with no visible feedback. This renders
    both on every page that uses this layout. --}}
    @if (session('success'))
        <div class="max-w-4xl mx-auto mt-4 px-4">
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="max-w-4xl mx-auto mt-4 px-4">
            <div class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                {{ session('error') }}
            </div>
        </div>
    @endif

    <main>
        {{ $slot }}
    </main>
</body>
</html>