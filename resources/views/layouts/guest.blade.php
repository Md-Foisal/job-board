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
            <a href="{{ route('job-listings.index') }}" class="text-sm text-gray-600 hover:text-blue-600">Find jobs</a>
        
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
    <main>
        {{ $slot }}
    </main>
</body>
</html>