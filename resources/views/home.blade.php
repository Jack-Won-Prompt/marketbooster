@extends('layouts.site')

@section('title', '공공데이터 기반 상권분석')

@section('content')

{{-- ─── 히어로 ────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden border-b border-line-soft bg-gradient-to-b from-brand-50/70 to-white">
    <div class="container-page grid items-center gap-14 py-20 lg:grid-cols-[1.05fr_1fr] lg:py-28">
        <div>
            <p class="eyebrow">Location Intelligence Platform</p>
            <h1 class="mt-5 text-[38px] font-extrabold leading-[1.18] tracking-tight text-ink-900 sm:text-[52px]">
                국내 공공데이터를 하나로,<br>
                <span class="text-brand-500">상권을 숫자로</span> 읽습니다
            </h1>
            <p class="mt-6 max-w-xl text-[17px] leading-relaxed text-ink-500">
                지역을 고르기만 하면 유동인구·카드매출·배후세대·직장인구·학생 수를 한 번에 집계해
                행정동 단위 상권분석 리포트를 만들어 드립니다. PDF로 바로 내려받아 보고서에 쓰세요.
            </p>

            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="btn-primary px-7 py-3 text-[15px]">무료로 리포트 받기</a>
                <a href="{{ route('solution') }}" class="btn-ghost px-7 py-3 text-[15px]">
                    솔루션 둘러보기
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
                    </svg>
                </a>
            </div>

            <div class="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3 text-[13px] font-medium text-ink-400">
                <span class="flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent-mint"></span>공공데이터포털 연동
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent-mint"></span>행정동 {{ number_format($stats['regions']) }}곳 수록
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent-mint"></span>반경 300m ~ 3km 분석
                </span>
            </div>
        </div>

        {{-- 리포트 미리보기 목업 --}}
        <div class="relative">
            <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-accent-cyan/20 blur-3xl"></div>
            <div class="relative rounded-2xl border border-line bg-white p-6 shadow-[0_24px_60px_-24px_rgba(16,15,20,0.28)]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[12px] font-bold tracking-wide text-brand-500">상권분석 리포트</p>
                        <p class="mt-1 text-[17px] font-extrabold text-ink-900">마곡나루역 반경 1,000m</p>
                    </div>
                    <span class="chip">2026년 8월 기준</span>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    @foreach ([['거주인구', '23,015', '명'], ['배후세대', '9,266', '세대'], ['점심 유동인구', '45,499', '명'], ['직장인구', '9,868', '명']] as [$label, $value, $unit])
                        <div class="rounded-xl border border-line-soft bg-surface-muted px-4 py-3.5">
                            <p class="text-[12px] font-semibold text-ink-400">{{ $label }}</p>
                            <p class="mt-1 text-[22px] font-extrabold tabular-nums text-ink-900">
                                {{ $value }}<span class="ml-0.5 text-[13px] font-semibold text-ink-400">{{ $unit }}</span>
                            </p>
                        </div>
                    @endforeach
                </div>

                {{-- 시간대별 유동인구 미니 차트 --}}
                <div class="mt-5 rounded-xl border border-line-soft p-4">
                    <p class="text-[12px] font-bold text-ink-500">시간대별 유동인구</p>
                    <div class="mt-4 flex h-24 items-end gap-2">
                        @foreach ([62, 84, 100, 76, 63, 34] as $height)
                            <div class="flex-1 rounded-t-md bg-gradient-to-t from-brand-500/70 to-brand-500"
                                 style="height: {{ $height }}%"></div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex justify-between text-[11px] text-ink-300">
                        <span>오전</span><span>점심</span><span>오후</span><span>저녁</span><span>밤</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─── 숫자 블록 ─────────────────────────────────────────────── --}}
<section class="border-b border-line-soft bg-white">
    <div class="container-page py-20">
        <div class="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
            <div>
                <p class="text-[64px] font-black leading-none text-brand-500 sm:text-[86px]">70%</p>
                <p class="mt-4 text-[20px] font-extrabold leading-snug text-ink-900">
                    국내 소비의 대부분은<br>여전히 오프라인 상권에서 일어납니다
                </p>
                <p class="mt-4 max-w-md text-[15px] leading-relaxed text-ink-500">
                    그런데 상권 데이터는 부처와 기관별로 흩어져 있습니다. MarketScope는 공개된 통계를
                    같은 기준(행정동 · 기준연월)으로 정규화해 한 장의 리포트로 묶습니다.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['수록 행정동', number_format($stats['regions']), '곳', '경계 폴리곤까지 포함'],
                    ['유동인구 셀', number_format($stats['floating_rows']), '건', '요일·시간대·성·연령'],
                    ['카드매출 셀', number_format($stats['sales_rows']), '건', '업종·요일·시간대'],
                ] as [$label, $value, $unit, $desc])
                    <div class="card-pad">
                        <p class="text-[13px] font-semibold text-ink-400">{{ $label }}</p>
                        <p class="mt-2 text-[30px] font-extrabold leading-none tabular-nums text-ink-900">
                            {{ $value }}<span class="ml-1 text-[14px] font-bold text-ink-400">{{ $unit }}</span>
                        </p>
                        <p class="mt-3 text-[13px] leading-relaxed text-ink-400">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ─── 솔루션 카드 ───────────────────────────────────────────── --}}
<section class="border-b border-line-soft bg-surface-muted">
    <div class="container-page py-20">
        <p class="eyebrow">Solutions</p>
        <h2 class="section-title mt-4">상권을 보는 네 가지 방법</h2>
        <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-ink-500">
            분석 목적에 따라 필요한 화면이 다릅니다. 리포트, 지도, 비교, API를 한 계정에서 씁니다.
        </p>

        <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Report', '상권분석 리포트', '거주·배후세대·직장·유동인구와 카드매출을 담은 PDF 리포트를 즉시 생성합니다.', 'M7 4h7l4 4v12H7z'],
                ['Map', '반경 · 행정동 선택', '지도에서 중심을 찍고 반경을 정하면 걸치는 행정동을 면적 비율로 안분합니다.', 'M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z'],
                ['Compare', '상위 지역 대비 비교', '같은 시도·시군구 행정동 평균과 나란히 놓아 상권 수준을 가늠합니다.', 'M5 19V9M12 19V5M19 19v-6'],
                ['Data API', '수집 파이프라인', '공공데이터포털 API와 CSV 파일데이터를 같은 스키마로 적재합니다.', 'M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3z'],
            ] as [$tag, $title, $desc, $icon])
                <div class="card group flex h-full flex-col p-6 transition hover:border-brand-400 hover:shadow-[0_18px_40px_-24px_rgba(5,147,255,0.5)]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                        </svg>
                    </span>
                    <p class="mt-5 text-[12px] font-bold tracking-wider text-brand-500 uppercase">{{ $tag }}</p>
                    <p class="mt-1.5 text-[17px] font-extrabold text-ink-900">{{ $title }}</p>
                    <p class="mt-3 flex-1 text-[14px] leading-relaxed text-ink-500">{{ $desc }}</p>
                    <a href="{{ route('solution') }}" class="mt-5 inline-flex items-center gap-1.5 text-[14px] font-bold text-brand-600">
                        바로가기
                        <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ─── 활용 시나리오 ──────────────────────────────────────────── --}}
<section class="border-b border-line-soft bg-white">
    <div class="container-page py-20">
        <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
                <p class="eyebrow">Use cases</p>
                <h2 class="section-title mt-4">이럴 때 씁니다</h2>
                <p class="mt-4 text-[15px] leading-relaxed text-ink-500">
                    출점 검토부터 가맹 상담, 임대 제안, 마케팅 타깃 설정까지 —
                    상권 숫자가 필요한 순간에 리포트 한 장이면 충분합니다.
                </p>
            </div>

            <div class="flex flex-wrap gap-2.5">
                @foreach ([
                    '신규 출점 후보지 검토', '가맹 상담 자료', '임대 제안서', '점포 이전 검토',
                    '배후세대 규모 확인', '직장인 상권 여부 판단', '점심 매출 잠재력', '저녁 상권 활성도',
                    '학원가 입지 분석', '학생 수 기반 수요 추정', '업종별 매출 비중', '경쟁 업종 밀도',
                    '주말 vs 평일 비교', '주 소비 연령대 파악', '시간대별 운영시간 설계', '배달 상권 판단',
                    '프랜차이즈 본사 리포트', '상권 리서치 보고서', '투자 검토 자료', '지자체 정책 자료',
                ] as $tag)
                    <span class="rounded-full border border-line bg-surface-muted px-4 py-2 text-[13px] font-medium text-ink-500 transition hover:border-brand-400 hover:text-brand-600">
                        {{ $tag }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ─── 데이터 출처 ───────────────────────────────────────────── --}}
<section class="border-b border-line-soft bg-surface-muted">
    <div class="container-page py-20">
        <p class="eyebrow">Data sources</p>
        <h2 class="section-title mt-4">공개된 데이터만 씁니다</h2>

        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['유동인구', '공공데이터포털 지역별 유동인구 통계'],
                ['카드매출', '공공데이터포털 지역별 카드매출 통계'],
                ['거주인구', '행정안전부 주민등록인구'],
                ['배후세대', '국토교통부 건축물대장'],
                ['직장인구', '통계청 전국사업체조사'],
                ['학생 수', '한국교육학술정보원 학교알리미'],
                ['학원 수', '지방행정인허가데이터 학원교습소'],
                ['행정동 경계', '행정안전부 행정동 경계 · 중심점'],
            ] as [$label, $provider])
                <div class="rounded-xl border border-line bg-white px-5 py-4">
                    <p class="text-[14px] font-extrabold text-ink-900">{{ $label }}</p>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-ink-400">{{ $provider }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ─── CTA ──────────────────────────────────────────────────── --}}
<section class="bg-ink-900">
    <div class="container-page flex flex-col items-start justify-between gap-8 py-16 lg:flex-row lg:items-center">
        <div>
            <h2 class="text-[28px] font-extrabold leading-snug text-white sm:text-[34px]">
                지금 지역을 하나 골라보세요
            </h2>
            <p class="mt-3 text-[15px] text-ink-300">가입 후 바로 첫 리포트를 만들 수 있습니다. 카드 등록이 필요 없습니다.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('register') }}" class="btn bg-brand-500 px-7 py-3 text-[15px] text-white hover:bg-brand-600">무료로 시작하기</a>
            <a href="{{ route('login') }}" class="btn border border-white/20 px-7 py-3 text-[15px] text-white hover:bg-white/10">로그인</a>
        </div>
    </div>
</section>

@endsection
