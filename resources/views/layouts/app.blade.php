<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '워크스페이스') · {{ config('app.name') }}</title>

    {{-- 스크립트가 살아 있을 때만 스크롤 등장 효과를 켠다 (JS 실패 시 본문이 숨지 않도록) --}}
    <script>document.documentElement.classList.add('js-reveal');</script>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-surface-muted">

<div class="flex min-h-screen">
    {{-- 사이드바 --}}
    <aside class="hidden w-60 shrink-0 border-r border-line-soft bg-white lg:flex lg:flex-col">
        <a href="{{ route('home') }}" class="flex h-16 items-center gap-2.5 border-b border-line-soft px-5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-[15px] font-black text-white">M</span>
            <span class="text-[17px] font-extrabold tracking-tight text-ink-900">MarketScope</span>
        </a>

        <nav class="flex-1 space-y-1 p-3">
            @php
                $nav = [
                    ['dashboard', '대시보드', 'M4 6h6v6H4zM14 6h6v4h-6zM14 14h6v4h-6zM4 16h6v2H4z'],
                    ['analyses.create', '새 상권분석', 'M12 5v14M5 12h14'],
                    ['analyses.index', '분석 목록', 'M4 7h16M4 12h16M4 17h10'],
                ];
            @endphp

            @foreach ($nav as [$route, $label, $path])
                <a href="{{ route($route) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition
                          {{ request()->routeIs($route) ? 'bg-brand-50 text-brand-600' : 'text-ink-500 hover:bg-surface-muted hover:text-ink-900' }}">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/>
                    </svg>
                    {{ $label }}
                </a>
            @endforeach

            @if (auth()->user()?->isAdmin())
                <div class="!mt-5 border-t border-line-soft pt-4">
                    <p class="px-3 pb-2 text-[11px] font-bold tracking-wider text-ink-300">관리</p>
                    <a href="{{ route('admin.data') }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition
                              {{ request()->routeIs('admin.data') ? 'bg-brand-50 text-brand-600' : 'text-ink-500 hover:bg-surface-muted hover:text-ink-900' }}">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>
                        </svg>
                        데이터 현황
                    </a>
                </div>
            @endif
        </nav>

        <div class="border-t border-line-soft p-3">
            <div class="rounded-xl bg-surface-muted p-3">
                <p class="truncate text-sm font-bold text-ink-900">{{ auth()->user()->name }}</p>
                <p class="truncate text-[12px] text-ink-400">{{ auth()->user()->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] font-semibold text-ink-500 hover:text-ink-900">
                        로그아웃
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- 상단 바 --}}
        <header class="sticky top-0 z-40 flex h-16 items-center justify-between gap-4 border-b border-line-soft bg-white px-5 lg:px-8">
            <div class="min-w-0">
                <h1 class="truncate text-[18px] font-extrabold text-ink-900">@yield('heading', '워크스페이스')</h1>
                @hasSection('subheading')
                    <p class="truncate text-[13px] text-ink-400">@yield('subheading')</p>
                @endif
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @yield('actions')
                <a href="{{ route('analyses.create') }}" class="btn-primary btn-sm lg:hidden">분석</a>
            </div>
        </header>

        <main class="flex-1 p-5 lg:p-8">
            @if (session('status'))
                <div class="mb-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

</body>
</html>
