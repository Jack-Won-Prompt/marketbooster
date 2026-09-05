<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '상권분석 플랫폼') · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', '공공데이터 기반 유동인구·카드매출 상권분석 리포트를 몇 초 만에 받아보세요.')">

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white">

<header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-line-soft bg-white/90 backdrop-blur">
    <div class="container-page flex h-16 items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-[15px] font-black text-white">M</span>
            <span class="text-[19px] font-extrabold tracking-tight text-ink-900">MarketScope</span>
        </a>

        <nav class="hidden items-center gap-8 md:flex">
            @foreach ([['solution', '솔루션'], ['data', '데이터'], ['pricing', '요금제']] as [$route, $label])
                <a href="{{ route($route) }}"
                   class="text-[15px] font-semibold transition hover:text-brand-600 {{ request()->routeIs($route) ? 'text-brand-600' : 'text-ink-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-3 md:flex">
            @auth
                <a href="{{ route('dashboard') }}" class="text-[15px] font-semibold text-ink-700 hover:text-brand-600">내 워크스페이스</a>
                <a href="{{ route('analyses.create') }}" class="btn-primary btn-sm">상권분석 시작</a>
            @else
                <a href="{{ route('login') }}" class="text-[15px] font-semibold text-ink-700 hover:text-brand-600">로그인</a>
                <a href="{{ route('register') }}" class="btn-primary btn-sm">무료로 시작하기</a>
            @endauth
        </div>

        <button type="button" class="md:hidden" @click="open = !open" aria-label="메뉴 열기">
            <svg class="h-6 w-6 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
            </svg>
        </button>
    </div>

    <div x-show="open" x-cloak class="border-t border-line-soft bg-white md:hidden">
        <div class="container-page flex flex-col gap-1 py-3">
            @foreach ([['solution', '솔루션'], ['data', '데이터'], ['pricing', '요금제']] as [$route, $label])
                <a href="{{ route($route) }}" class="rounded-lg px-2 py-2.5 text-[15px] font-semibold text-ink-700 hover:bg-surface-muted">{{ $label }}</a>
            @endforeach
            <div class="mt-2 flex gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-ghost btn-sm flex-1">워크스페이스</a>
                    <a href="{{ route('analyses.create') }}" class="btn-primary btn-sm flex-1">분석 시작</a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost btn-sm flex-1">로그인</a>
                    <a href="{{ route('register') }}" class="btn-primary btn-sm flex-1">무료로 시작하기</a>
                @endauth
            </div>
        </div>
    </div>
</header>

@if (session('status'))
    <div class="container-page pt-4">
        <div class="rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700">
            {{ session('status') }}
        </div>
    </div>
@endif

<main>
    @yield('content')
</main>

<footer class="mt-24 border-t border-line-soft bg-surface-muted">
    <div class="container-page grid gap-10 py-14 md:grid-cols-4">
        <div class="md:col-span-2">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-[15px] font-black text-white">M</span>
                <span class="text-[19px] font-extrabold text-ink-900">MarketScope</span>
            </div>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-ink-500">
                공공데이터포털의 지역 유동인구·카드매출 공개데이터를 수집해
                행정동 단위 상권 리포트로 만들어 드립니다.
            </p>
        </div>

        <div>
            <p class="text-[13px] font-bold text-ink-900">서비스</p>
            <ul class="mt-4 space-y-2.5 text-sm text-ink-500">
                <li><a href="{{ route('solution') }}" class="hover:text-brand-600">솔루션</a></li>
                <li><a href="{{ route('data') }}" class="hover:text-brand-600">데이터</a></li>
                <li><a href="{{ route('pricing') }}" class="hover:text-brand-600">요금제</a></li>
            </ul>
        </div>

        <div>
            <p class="text-[13px] font-bold text-ink-900">데이터 출처</p>
            <ul class="mt-4 space-y-2.5 text-sm text-ink-500">
                <li>공공데이터포털 (data.go.kr)</li>
                <li>행정안전부 주민등록인구</li>
                <li>국토교통부 건축물대장</li>
                <li>한국교육학술정보원 학교알리미</li>
            </ul>
        </div>
    </div>

    <div class="border-t border-line">
        <div class="container-page flex flex-col gap-2 py-6 text-[13px] text-ink-400 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ date('Y') }} MarketScope. 모든 통계는 집계 기준에 따라 오차가 있을 수 있어 참고용으로 활용해 주세요.</p>
            <p>가맹사업 서면 제공 시에는 가맹사업법이 정한 양식과 기준을 따르시기 바랍니다.</p>
        </div>
    </div>
</footer>

</body>
</html>
