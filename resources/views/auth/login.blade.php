@extends('layouts.site')

@section('title', '로그인')

@section('content')
<section class="container-page flex justify-center py-16 lg:py-24">
    <div class="w-full max-w-md">
        <h1 class="text-[28px] font-extrabold tracking-tight text-ink-900">로그인</h1>
        <p class="mt-2 text-[15px] text-ink-500">MarketScope 워크스페이스로 이동합니다.</p>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label class="label" for="email">이메일</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" class="input" placeholder="you@company.com">
            </div>

            <div>
                <label class="label" for="password">비밀번호</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="input" placeholder="••••••••">
            </div>

            <label class="flex items-center gap-2 text-[13px] text-ink-500">
                <input type="checkbox" name="remember" value="1"
                       class="h-4 w-4 rounded border-line text-brand-500 focus:ring-brand-500/30">
                로그인 상태 유지
            </label>

            <button type="submit" class="btn-primary w-full py-3 text-[15px]">로그인</button>
        </form>

        <p class="mt-6 text-center text-[14px] text-ink-500">
            아직 계정이 없으신가요?
            <a href="{{ route('register') }}" class="font-bold text-brand-600 hover:underline">무료로 시작하기</a>
        </p>

        <div class="mt-8 rounded-xl border border-line-soft bg-surface-muted px-4 py-3 text-[13px] leading-relaxed text-ink-500">
            <span class="font-bold text-ink-700">데모 계정</span> ·
            demo@marketscope.test / demo1234
        </div>
    </div>
</section>
@endsection
