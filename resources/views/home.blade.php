@extends('layouts.site')

@section('title', '공공데이터 기반 상권분석')

@section('content')

{{-- ─── 히어로 ────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden border-b border-line-soft bg-white">
    {{-- 배경 --}}
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="blob -left-24 top-[-120px] h-[420px] w-[420px] bg-brand-100"></div>
        <div class="blob right-[-140px] top-[60px] h-[460px] w-[460px] bg-accent-cyan/25" style="animation-delay: -6s"></div>
        <div class="blob bottom-[-180px] left-1/3 h-[380px] w-[380px] bg-accent-mint/20" style="animation-delay: -12s"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-white/30 via-white/70 to-white"></div>
    </div>

    <div class="container-page relative grid items-center gap-14 py-20 lg:grid-cols-[1.02fr_1.08fr] lg:py-28">
        <div>
            <p class="eyebrow" data-reveal>Location Intelligence Platform</p>

            <h1 class="mt-5 text-[38px] font-extrabold leading-[1.18] tracking-tight text-ink-900 sm:text-[52px]"
                data-reveal data-reveal-delay="60">
                국내 공공데이터를 하나로,<br>
                <span class="shine">상권을 숫자로</span> 읽습니다
            </h1>

            <p class="mt-6 max-w-xl text-[17px] leading-relaxed text-ink-500" data-reveal data-reveal-delay="140">
                지역을 고르기만 하면 유동인구·카드매출·배후세대·직장인구·학생 수를 한 번에 집계해
                행정동 단위 상권분석 리포트를 만들어 드립니다. PDF로 바로 내려받아 보고서에 쓰세요.
            </p>

            <div class="mt-9 flex flex-wrap gap-3" data-reveal data-reveal-delay="220">
                <a href="{{ route('register') }}" class="btn-primary group px-7 py-3 text-[15px]">
                    무료로 리포트 받기
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
                    </svg>
                </a>
                <a href="#how" class="btn-ghost px-7 py-3 text-[15px]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 9l5 3-5 3z"/>
                    </svg>
                    3단계로 보기
                </a>
            </div>

            <div class="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3 text-[13px] font-medium text-ink-400"
                 data-reveal data-reveal-delay="300">
                @foreach (['공공데이터포털 연동', '행정동 '.number_format($stats['regions']).'곳 수록', '반경 300m ~ 3km 분석'] as $item)
                    <span class="flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-accent-mint"></span>{{ $item }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- 히어로 이미지 --}}
        <div class="relative" data-reveal="zoom" data-reveal-delay="120">
            <div class="floaty-slow relative rounded-[22px] shadow-[0_36px_90px_-40px_rgba(16,15,20,0.42)]">
                <img src="{{ asset('images/hero-map.svg') }}" width="720" height="560" fetchpriority="high"
                     alt="분석 반경 안의 행정동과 유동인구 밀도가 표시된 지도"
                     class="w-full rounded-[22px]">
            </div>

            <div class="floaty absolute -bottom-6 -left-4 hidden rounded-2xl border border-line bg-white/95 px-5 py-4 shadow-lg backdrop-blur sm:block"
                 style="animation-delay: -3s">
                <p class="text-[11px] font-semibold text-ink-400">겹침 비율 안분</p>
                <p class="mt-1 text-[15px] font-extrabold text-ink-900">행정동 3곳 · 42% / 42% / 3%</p>
            </div>
        </div>
    </div>

    <a href="#sources" class="relative mx-auto mb-8 hidden w-fit lg:block" aria-label="아래로 이동">
        <svg class="scroll-hint h-6 w-6 text-ink-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M6 13l6 6 6-6"/>
        </svg>
    </a>
</section>

{{-- ─── 데이터 출처 흐름 띠 ────────────────────────────────────── --}}
<section id="sources" class="border-b border-line-soft bg-surface-muted py-10">
    <p class="container-page text-center text-[12px] font-bold tracking-[0.14em] text-ink-300 uppercase">
        Data sources
    </p>

    @php
        $sourceBadges = [
            ['공공데이터포털', 'data.go.kr'],
            ['행정안전부', '주민등록인구'],
            ['국토교통부', '건축물대장'],
            ['통계청', '전국사업체조사'],
            ['SKT 지오비전', '유동인구'],
            ['한국교육학술정보원', '학교알리미'],
            ['지방행정인허가데이터', '학원교습소'],
            ['행정안전부', '행정동 경계'],
        ];
    @endphp

    <div class="marquee mt-6">
        <div class="marquee-track">
            @foreach ([1, 2] as $pass)
                <div class="flex shrink-0 gap-3 pr-3" @if ($pass === 2) aria-hidden="true" @endif>
                    @foreach ($sourceBadges as [$name, $desc])
                        <div class="flex items-center gap-3 rounded-xl border border-line bg-white px-5 py-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-[13px] font-black text-brand-600">
                                {{ mb_substr($name, 0, 1) }}
                            </span>
                            <span>
                                <span class="block whitespace-nowrap text-[13px] font-bold text-ink-900">{{ $name }}</span>
                                <span class="block whitespace-nowrap text-[11px] text-ink-400">{{ $desc }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ─── 숫자 블록 ─────────────────────────────────────────────── --}}
<section class="border-b border-line-soft bg-white">
    <div class="container-page py-20">
        <div class="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
            <div data-reveal="left">
                <p class="text-[64px] font-black leading-none text-brand-500 sm:text-[86px]">
                    <span data-count-to="70" data-count-suffix="%">0%</span>
                </p>
                <p class="mt-4 text-[20px] font-extrabold leading-snug text-ink-900">
                    국내 소비의 대부분은<br>여전히 오프라인 상권에서 일어납니다
                </p>
                <p class="mt-4 max-w-md text-[15px] leading-relaxed text-ink-500">
                    그런데 상권 데이터는 부처와 기관별로 흩어져 있습니다. MarketScope는 공개된 통계를
                    같은 기준(행정동 · 기준연월)으로 정규화해 한 장의 리포트로 묶습니다.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3" data-reveal-stagger="110">
                @foreach ([
                    ['수록 행정동', $stats['regions'], '곳', '경계 폴리곤까지 포함'],
                    ['유동인구 셀', $stats['floating_rows'], '건', '요일·시간대·성·연령'],
                    ['카드매출 셀', $stats['sales_rows'], '건', '업종·요일·시간대'],
                ] as [$label, $value, $unit, $desc])
                    <div class="card-pad lift">
                        <p class="text-[13px] font-semibold text-ink-400">{{ $label }}</p>
                        <p class="mt-2 text-[30px] font-extrabold leading-none tabular-nums text-ink-900">
                            <span data-count-to="{{ $value }}">0</span><span class="ml-1 text-[14px] font-bold text-ink-400">{{ $unit }}</span>
                        </p>
                        <p class="mt-3 text-[13px] leading-relaxed text-ink-400">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ─── 3단계 진행 (자동 전환) ─────────────────────────────────── --}}
@php
    $steps = [
        ['지역을 고릅니다', '지도에서 중심을 찍고 반경을 정하거나, 행정동을 직접 선택합니다. 원에 걸치는 행정동과 겹침 비율을 실행 전에 미리 보여 드립니다.', 'step-1-select.svg', '지역 선택 화면'],
        ['통계를 안분해 집계합니다', '행정동 경계에 격자점을 뿌려 실제 겹친 면적 비율을 구하고, 인구·매출·교육 지표를 그 비율만큼 나눠 합산합니다.', 'step-2-analyze.svg', '집계 진행 화면'],
        ['리포트를 받습니다', '상위 시도·시군구 평균과 나란히 놓인 리포트가 완성됩니다. 웹에서 바로 보고, 같은 내용을 PDF로 내려받습니다.', 'step-3-report.svg', '완성된 리포트 화면'],
    ];
@endphp

<section id="how" class="scroll-mt-20 border-b border-line-soft bg-surface-muted">
    <div class="container-page py-20">
        <div class="max-w-2xl" data-reveal>
            <p class="eyebrow">How it works</p>
            <h2 class="section-title mt-4">지역 하나 고르면, 3단계로 끝납니다</h2>
        </div>

        <div class="mt-12 grid gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-center"
             x-data="{
                 step: 0,
                 timer: null,
                 total: {{ count($steps) }},
                 play() {
                     if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                     this.timer = setInterval(() => { this.step = (this.step + 1) % this.total }, 5200);
                 },
                 pause() { clearInterval(this.timer) },
                 select(index) { this.pause(); this.step = index; this.play(); }
             }"
             x-init="play()" @mouseenter="pause()" @mouseleave="play()">

            {{-- 단계 목록 --}}
            <ol class="space-y-3" data-reveal="left">
                @foreach ($steps as $index => [$title, $desc, $image, $alt])
                    <li>
                        <button type="button" @click="select({{ $index }})"
                                class="w-full rounded-2xl border p-5 text-left transition"
                                :class="step === {{ $index }}
                                    ? 'border-brand-500 bg-white shadow-[0_18px_40px_-28px_rgba(5,147,255,0.55)]'
                                    : 'border-transparent bg-white/60 hover:bg-white'">
                            <div class="flex items-start gap-4">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[13px] font-black transition"
                                      :class="step === {{ $index }} ? 'bg-brand-500 text-white' : 'bg-surface-sunken text-ink-400'">
                                    {{ $index + 1 }}
                                </span>
                                <div>
                                    <p class="text-[16px] font-extrabold text-ink-900">{{ $title }}</p>
                                    <p class="mt-1.5 text-[14px] leading-relaxed text-ink-500"
                                       x-show="step === {{ $index }}" x-cloak
                                       x-transition:enter="transition duration-400"
                                       x-transition:enter-start="opacity-0 -translate-y-1"
                                       x-transition:enter-end="opacity-100 translate-y-0">{{ $desc }}</p>
                                </div>
                            </div>

                            {{-- 진행 바 — template x-if 라 단계가 바뀔 때마다 다시 채워진다 --}}
                            <template x-if="step === {{ $index }}">
                                <div class="mt-4 h-1 overflow-hidden rounded-full bg-surface-sunken">
                                    <div class="progress-run h-full rounded-full bg-brand-500"></div>
                                </div>
                            </template>
                        </button>
                    </li>
                @endforeach
            </ol>

            {{-- 화면 이미지 --}}
            <div class="relative" data-reveal="right">
                <div class="absolute -inset-4 rounded-3xl bg-gradient-to-br from-brand-100/70 to-accent-cyan/20 blur-2xl" aria-hidden="true"></div>

                <div class="relative aspect-[560/380]">
                    @foreach ($steps as $index => [$title, $desc, $image, $alt])
                        <img src="{{ asset('images/'.$image) }}" width="560" height="380" loading="lazy"
                             alt="{{ $alt }}"
                             class="absolute inset-0 h-full w-full rounded-2xl border border-line bg-white shadow-xl transition-all duration-500"
                             :class="step === {{ $index }} ? 'opacity-100 scale-100' : 'pointer-events-none opacity-0 scale-[0.97]'">
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─── 솔루션 카드 ───────────────────────────────────────────── --}}
<section class="border-b border-line-soft bg-white">
    <div class="container-page py-20">
        <div class="max-w-2xl" data-reveal>
            <p class="eyebrow">Solutions</p>
            <h2 class="section-title mt-4">상권을 보는 네 가지 방법</h2>
            <p class="mt-4 text-[15px] leading-relaxed text-ink-500">
                분석 목적에 따라 필요한 화면이 다릅니다. 리포트, 지도, 비교, 수집 파이프라인을 한 계정에서 씁니다.
            </p>
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-2" data-reveal-stagger="90">
            @foreach ([
                ['Report', '상권분석 리포트', '거주·배후세대·직장·유동인구와 카드매출을 담은 리포트를 즉시 생성하고 PDF로 내려받습니다.', 'illus-report.svg', '막대 차트가 그려지는 리포트'],
                ['Map', '반경 · 행정동 선택', '지도에서 중심을 찍고 반경을 정하면 걸치는 행정동을 실제 경계 기준으로 안분합니다.', 'illus-radius.svg', '행정동 경계에 겹치는 분석 반경'],
                ['Compare', '상위 지역 대비 비교', '같은 시도·시군구 행정동 평균과 나란히 놓아 상권 수준을 5단계로 판정합니다.', 'illus-compare.svg', '선택지역과 평균을 비교하는 차트'],
                ['Pipeline', '데이터 수집', '공공데이터포털 오픈 API와 CSV 파일데이터를 같은 스키마로 정규화해 적재합니다.', 'illus-pipeline.svg', '공공데이터가 정규화를 거쳐 적재되는 흐름'],
            ] as [$tag, $title, $desc, $image, $alt])
                <a href="{{ route('solution') }}"
                   class="card lift media-zoom group flex flex-col overflow-hidden hover:border-brand-400">
                    {{-- 삽화 비율(320:200)을 그대로 유지해야 안쪽 라벨이 잘리지 않는다 --}}
                    <img src="{{ asset('images/'.$image) }}" width="320" height="200" loading="lazy" alt="{{ $alt }}"
                         class="aspect-[320/200] w-full object-cover">

                    <div class="flex flex-1 flex-col border-t border-line-soft p-6">
                        <p class="text-[12px] font-bold tracking-wider text-brand-500 uppercase">{{ $tag }}</p>
                        <p class="mt-1.5 text-[17px] font-extrabold text-ink-900">{{ $title }}</p>
                        <p class="mt-3 flex-1 text-[14px] leading-relaxed text-ink-500">{{ $desc }}</p>
                        <span class="mt-5 inline-flex items-center gap-1.5 text-[14px] font-bold text-brand-600">
                            바로가기
                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
                            </svg>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ─── 리포트 미리보기 (탭) ──────────────────────────────────── --}}
@php
    $preview = [
        'population' => [
            'label' => '인구',
            'note' => '성 · 연령 교차, 상위 지역 평균 대비',
            'rows' => [
                ['20대', 58, 62], ['30대', 92, 96], ['40대', 78, 76], ['50대', 64, 66], ['60대', 44, 52],
            ],
            'legend' => [['남성', '#0593ff'], ['여성', '#f2557b']],
        ],
        'sales' => [
            'label' => '카드매출',
            'note' => '업종별 매출 비중',
            'rows' => [
                ['한식음식점', 100, null], ['일반의원', 66, null], ['편의점', 51, null],
                ['슈퍼마켓', 37, null], ['의류·잡화', 34, null],
            ],
            'legend' => [['매출액', '#0593ff']],
        ],
        'education' => [
            'label' => '학생 · 학원',
            'note' => '학교급별 학생 수',
            'rows' => [
                ['어린이집', 62, null], ['유치원', 18, null], ['초등학생', 100, null],
                ['중학생', 44, null], ['고등학생', 0, null],
            ],
            'legend' => [['학생 수', '#00599d']],
        ],
    ];
@endphp

<section class="border-b border-line-soft bg-surface-muted">
    <div class="container-page py-20">
        <div class="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
            <div data-reveal="left">
                <p class="eyebrow">Report preview</p>
                <h2 class="section-title mt-4">리포트에 무엇이 담기나요</h2>
                <p class="mt-4 text-[15px] leading-relaxed text-ink-500">
                    분석 결과 문단까지 자동으로 채워집니다. 탭을 눌러 섹션별로 어떤 형태인지 확인해 보세요.
                </p>

                <ul class="mt-8 space-y-3">
                    @foreach ([
                        '거주인구 · 배후세대 · 직장인구 · 유동인구',
                        '업종별 · 요일/시간대별 · 성·연령별 카드매출',
                        '학교급별 학생 수와 학원 업종 분포',
                        '시도 · 시군구 행정동 평균 대비 수준 판정',
                    ] as $item)
                        <li class="flex items-start gap-2.5 text-[14px] text-ink-700">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card p-6 sm:p-8" data-reveal="right" x-data="{ tab: 'population' }">
                <div class="flex flex-wrap gap-2">
                    @foreach ($preview as $key => $panel)
                        <button type="button" @click="tab = '{{ $key }}'"
                                class="rounded-full border px-4 py-2 text-[13px] font-semibold transition"
                                :class="tab === '{{ $key }}'
                                    ? 'border-brand-500 bg-brand-50 text-brand-600'
                                    : 'border-line text-ink-500 hover:border-brand-400'">
                            {{ $panel['label'] }}
                        </button>
                    @endforeach
                </div>

                @foreach ($preview as $key => $panel)
                    <div x-show="tab === '{{ $key }}'" x-cloak
                         x-transition:enter="transition duration-400"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="mt-6 text-[12px] font-semibold text-ink-400">{{ $panel['note'] }}</p>

                        <div class="mt-4 space-y-3.5">
                            @foreach ($panel['rows'] as [$label, $a, $b])
                                <div>
                                    <div class="flex items-center justify-between text-[13px]">
                                        <span class="font-semibold text-ink-700">{{ $label }}</span>
                                        @if ($a === 0)
                                            <span class="text-ink-300">-</span>
                                        @endif
                                    </div>
                                    {{-- 폭만 Alpine 이 바꾸도록 객체 문법을 쓴다. 문자열 :style 은 style 속성을 통째로 덮어쓴다. --}}
                                    <div class="mt-1.5 space-y-1.5">
                                        <div class="h-2.5 rounded-full bg-surface-sunken">
                                            <div class="h-full rounded-full transition-[width] duration-700"
                                                 style="background-color: {{ $panel['legend'][0][1] }}"
                                                 :style="{ width: tab === '{{ $key }}' ? '{{ $a }}%' : '0%' }"></div>
                                        </div>
                                        @if ($b !== null)
                                            <div class="h-2.5 rounded-full bg-surface-sunken">
                                                <div class="h-full rounded-full transition-[width] delay-75 duration-700"
                                                     style="background-color: #f2557b"
                                                     :style="{ width: tab === '{{ $key }}' ? '{{ $b }}%' : '0%' }"></div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 flex flex-wrap gap-4 border-t border-line-soft pt-4">
                            @foreach ($panel['legend'] as [$name, $color])
                                <span class="flex items-center gap-2 text-[12px] text-ink-400">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $color }}"></span>{{ $name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ─── 활용 시나리오 ──────────────────────────────────────────── --}}
<section class="border-b border-line-soft bg-white">
    <div class="container-page py-20">
        <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr]">
            <div data-reveal="left">
                <p class="eyebrow">Use cases</p>
                <h2 class="section-title mt-4">이럴 때 씁니다</h2>
                <p class="mt-4 text-[15px] leading-relaxed text-ink-500">
                    출점 검토부터 가맹 상담, 임대 제안, 마케팅 타깃 설정까지 —
                    상권 숫자가 필요한 순간에 리포트 한 장이면 충분합니다.
                </p>
            </div>

            <div class="flex flex-wrap gap-2.5" data-reveal-stagger="35">
                @foreach ([
                    '신규 출점 후보지 검토', '가맹 상담 자료', '임대 제안서', '점포 이전 검토',
                    '배후세대 규모 확인', '직장인 상권 여부 판단', '점심 매출 잠재력', '저녁 상권 활성도',
                    '학원가 입지 분석', '학생 수 기반 수요 추정', '업종별 매출 비중', '경쟁 업종 밀도',
                    '주말 vs 평일 비교', '주 소비 연령대 파악', '시간대별 운영시간 설계', '배달 상권 판단',
                    '프랜차이즈 본사 리포트', '상권 리서치 보고서', '투자 검토 자료', '지자체 정책 자료',
                ] as $tag)
                    <span class="cursor-default rounded-full border border-line bg-surface-muted px-4 py-2 text-[13px] font-medium text-ink-500 transition duration-300 hover:-translate-y-0.5 hover:border-brand-400 hover:bg-brand-50 hover:text-brand-600">
                        {{ $tag }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ─── CTA ──────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden bg-ink-900">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="blob left-[8%] top-[-120px] h-[320px] w-[320px] bg-brand-500/40" data-parallax="0.06"></div>
        <div class="blob right-[4%] bottom-[-160px] h-[340px] w-[340px] bg-accent-cyan/25" data-parallax="-0.05"></div>
    </div>

    <div class="container-page relative flex flex-col items-start justify-between gap-8 py-16 lg:flex-row lg:items-center">
        <div data-reveal>
            <h2 class="text-[28px] font-extrabold leading-snug text-white sm:text-[34px]">
                지금 지역을 하나 골라보세요
            </h2>
            <p class="mt-3 text-[15px] text-ink-300">가입 후 바로 첫 리포트를 만들 수 있습니다. 카드 등록이 필요 없습니다.</p>
        </div>
        <div class="flex gap-3" data-reveal data-reveal-delay="120">
            <a href="{{ route('register') }}" class="btn bg-brand-500 px-7 py-3 text-[15px] text-white hover:bg-brand-600">무료로 시작하기</a>
            <a href="{{ route('login') }}" class="btn border border-white/20 px-7 py-3 text-[15px] text-white hover:bg-white/10">로그인</a>
        </div>
    </div>
</section>

@endsection
