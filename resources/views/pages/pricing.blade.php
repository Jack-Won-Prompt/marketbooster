@extends('layouts.site')

@section('title', '요금제')

@section('content')
<section class="relative overflow-hidden border-b border-line-soft bg-white">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="blob left-1/4 top-[-160px] h-[400px] w-[400px] bg-brand-100"></div>
        <div class="blob right-[10%] top-[-40px] h-[320px] w-[320px] bg-accent-cyan/20" style="animation-delay: -9s"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-white/40 to-white"></div>
    </div>
    <div class="container-page relative py-20 text-center lg:py-24">
        <p class="eyebrow" data-reveal>Pricing</p>
        <h1 class="section-title mt-4" data-reveal data-reveal-delay="60">필요한 만큼만 쓰세요</h1>
        <p class="mx-auto mt-5 max-w-xl text-[16px] leading-relaxed text-ink-500" data-reveal data-reveal-delay="130">
            가입하면 바로 리포트를 만들 수 있습니다. 팀 단위 사용이나 데이터 연동이 필요하면 문의해 주세요.
        </p>
    </div>
</section>

<section class="container-page py-20">
    <div class="grid gap-6 lg:grid-cols-3" data-reveal-stagger="120">
        @foreach ([
            ['Free', '무료', '개인 사용자', ['월 5건 상권분석', '반경 · 행정동 분석', '웹 리포트 열람', '관심지역 5곳'], false],
            ['Pro', '월 49,000원', '점포 개발 · 컨설팅', ['월 100건 상권분석', 'PDF 리포트 다운로드', '카드매출 상세 분석', '관심지역 무제한', '분석 이력 보관'], true],
            ['Enterprise', '문의', '프랜차이즈 본사 · 기관', ['무제한 분석', '데이터 API 연동', '자체 데이터 적재', '전용 지원'], false],
        ] as [$name, $price, $target, $features, $featured])
            <div class="card lift flex flex-col p-8 {{ $featured ? 'border-brand-500 ring-1 ring-brand-500/20' : '' }}">
                @if ($featured)
                    <span class="mb-4 inline-flex w-fit rounded-full bg-brand-500 px-3 py-1 text-[11px] font-bold text-white">추천</span>
                @endif
                <p class="text-[15px] font-extrabold text-ink-900">{{ $name }}</p>
                <p class="mt-1 text-[13px] text-ink-400">{{ $target }}</p>
                <p class="mt-5 text-[30px] font-extrabold text-ink-900">{{ $price }}</p>

                <ul class="mt-7 flex-1 space-y-3">
                    @foreach ($features as $feature)
                        <li class="flex items-start gap-2.5 text-[14px] text-ink-700">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('register') }}" class="{{ $featured ? 'btn-primary' : 'btn-ghost' }} mt-8 w-full py-3">
                    {{ $name === 'Enterprise' ? '도입 문의' : '시작하기' }}
                </a>
            </div>
        @endforeach
    </div>

    <p class="mt-10 text-center text-[13px] text-ink-400">
        표시된 요금은 예시입니다. 실제 과금 연동은 포함되어 있지 않습니다.
    </p>
</section>
@endsection
