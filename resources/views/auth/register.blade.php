@extends('layouts.site')

@section('title', '회원가입')

@section('content')
<section class="container-page grid gap-16 py-16 lg:grid-cols-2 lg:py-24">
    <div class="hidden lg:block">
        <p class="eyebrow">Get started</p>
        <h1 class="section-title mt-4">3분이면 첫 리포트를<br>받아볼 수 있습니다</h1>
        <ul class="mt-8 space-y-4">
            @foreach ([
                ['지역만 고르면 끝', '지도에서 중심을 찍고 반경을 정하거나, 행정동을 직접 선택하세요.'],
                ['상위 지역과 자동 비교', '같은 시도·시군구 행정동 평균 대비 수준을 함께 보여 드립니다.'],
                ['PDF로 바로 활용', '보고서 형식 그대로 내려받아 제안서에 붙여 넣으세요.'],
            ] as [$title, $desc])
                <li class="flex gap-3.5">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-[15px] font-bold text-ink-900">{{ $title }}</p>
                        <p class="mt-1 text-[14px] leading-relaxed text-ink-500">{{ $desc }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="w-full max-w-md">
        <h2 class="text-[28px] font-extrabold tracking-tight text-ink-900">회원가입</h2>
        <p class="mt-2 text-[15px] text-ink-500">이메일만 있으면 바로 시작할 수 있습니다.</p>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">이름</label>
                    <input id="name" name="name" value="{{ old('name') }}" required autofocus class="input" placeholder="홍길동">
                </div>
                <div>
                    <label class="label" for="phone">연락처 <span class="font-normal text-ink-300">(선택)</span></label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" class="input" placeholder="010-0000-0000">
                </div>
            </div>

            <div>
                <label class="label" for="company">회사 / 소속 <span class="font-normal text-ink-300">(선택)</span></label>
                <input id="company" name="company" value="{{ old('company') }}" class="input" placeholder="○○컴퍼니">
            </div>

            <div>
                <label class="label" for="email">이메일</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       autocomplete="username" class="input" placeholder="you@company.com">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="password">비밀번호</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="input" placeholder="8자 이상">
                </div>
                <div>
                    <label class="label" for="password_confirmation">비밀번호 확인</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           autocomplete="new-password" class="input" placeholder="다시 입력">
                </div>
            </div>

            <div class="space-y-2.5 rounded-xl border border-line-soft bg-surface-muted p-4">
                <label class="flex items-start gap-2.5 text-[13px] text-ink-700">
                    <input type="checkbox" name="terms" value="1" {{ old('terms') ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-line text-brand-500 focus:ring-brand-500/30">
                    <span><span class="font-bold">[필수]</span> 이용약관 및 개인정보 처리방침에 동의합니다.</span>
                </label>
                <label class="flex items-start gap-2.5 text-[13px] text-ink-500">
                    <input type="checkbox" name="marketing" value="1" {{ old('marketing') ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-line text-brand-500 focus:ring-brand-500/30">
                    <span><span class="font-bold">[선택]</span> 신규 데이터 · 기능 소식을 이메일로 받겠습니다.</span>
                </label>
            </div>

            <button type="submit" class="btn-primary w-full py-3 text-[15px]">가입하고 분석 시작하기</button>
        </form>

        <p class="mt-6 text-center text-[14px] text-ink-500">
            이미 계정이 있으신가요?
            <a href="{{ route('login') }}" class="font-bold text-brand-600 hover:underline">로그인</a>
        </p>
    </div>
</section>
@endsection
