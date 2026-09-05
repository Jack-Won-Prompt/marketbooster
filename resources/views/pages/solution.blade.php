@extends('layouts.site')

@section('title', '솔루션')

@section('content')
<section class="relative overflow-hidden border-b border-line-soft bg-white">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="blob -left-20 top-[-140px] h-[380px] w-[380px] bg-brand-100"></div>
        <div class="blob right-[-120px] top-[-40px] h-[360px] w-[360px] bg-accent-cyan/25" style="animation-delay: -7s"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-white/40 to-white"></div>
    </div>

    <div class="container-page relative grid items-center gap-12 py-20 lg:grid-cols-[1fr_0.85fr] lg:py-24">
        <div>
            <p class="eyebrow" data-reveal>Solutions</p>
            <h1 class="section-title mt-4 max-w-3xl" data-reveal data-reveal-delay="60">
                지역만 고르면, 나머지는 플랫폼이 합니다
            </h1>
            <p class="mt-5 max-w-2xl text-[16px] leading-relaxed text-ink-500" data-reveal data-reveal-delay="130">
                흩어진 공공데이터를 행정동·기준연월이라는 같은 축으로 정규화해 두었기 때문에,
                반경 하나만 정해도 인구·매출·교육 지표가 한 번에 계산됩니다.
            </p>
        </div>

        <div data-reveal="zoom" data-reveal-delay="120">
            <img src="{{ asset('images/illus-radius.svg') }}" width="320" height="200" loading="eager"
                 alt="행정동 경계에 겹치는 분석 반경"
                 class="floaty-slow w-full rounded-2xl border border-line bg-white shadow-lg">
        </div>
    </div>
</section>

<section class="container-page py-20">
    <div class="space-y-16">
        @foreach ([
            [
                'Report',
                '상권분석 리포트',
                '거주인구·배후세대·직장인구·유동인구·카드매출·학생 수를 한 문서에 담습니다. 상위 시도/시군구 행정동 평균과 나란히 놓아 수준을 판단할 수 있고, PDF로 바로 내려받아 제안서에 붙일 수 있습니다.',
                ['성·연령 교차표', '요일 × 시간대 분포', '업종별 매출 비중', '자동 생성 분석 문단', 'PDF 다운로드'],
                'illus-report.svg',
                '막대 차트가 그려지는 상권분석 리포트',
            ],
            [
                'Map',
                '반경 · 행정동 선택',
                '지도에서 중심을 찍고 반경을 정하면 원에 걸친 행정동을 찾아냅니다. 행정동 경계 폴리곤에 격자점을 뿌려 실제로 겹친 면적 비율을 구하고, 그 비율만큼 통계를 안분합니다.',
                ['반경 300m ~ 3km', '행정동 직접 선택 (최대 30곳)', '겹침 비율 미리보기', '관심지역 저장'],
                'step-1-select.svg',
                '지도에서 중심을 찍고 반경을 고르는 화면',
            ],
            [
                'Compare',
                '상위 지역 대비 비교',
                '같은 시도와 시군구에 속한 행정동 1곳당 평균을 자동으로 계산해 기준선으로 씁니다. 절대 수치만으로는 알기 어려운 "이 상권이 큰 편인지"를 바로 판단할 수 있습니다.',
                ['시도 평균 비교', '시군구 평균 비교', '매우 높음 ~ 매우 낮음 5단계 판정'],
                'illus-compare.svg',
                '선택지역과 상위 지역 평균을 비교하는 차트',
            ],
            [
                'Data API',
                '수집 파이프라인',
                '공공데이터포털 오픈 API와 파일데이터(CSV)를 같은 스키마로 적재합니다. 기관마다 다른 필드명·코드값은 정규화 사전을 거쳐 내부 표준으로 변환되고, 재수집하면 같은 기준월 데이터가 갱신됩니다.',
                ['REST API 수집', 'CP949 CSV 적재', '필드명/코드 자동 매핑', '수집 이력 로그'],
                'illus-pipeline.svg',
                '공공데이터가 정규화를 거쳐 적재되는 흐름',
            ],
        ] as $index => [$tag, $title, $desc, $features, $image, $alt])
            <div class="grid items-center gap-10 lg:grid-cols-2 {{ $index % 2 ? 'lg:[direction:rtl]' : '' }}">
                <div class="[direction:ltr]" data-reveal="{{ $index % 2 ? 'right' : 'left' }}">
                    <p class="text-[12px] font-bold tracking-wider text-brand-500 uppercase">{{ $tag }}</p>
                    <h2 class="mt-2 text-[26px] font-extrabold text-ink-900">{{ $title }}</h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-ink-500">{{ $desc }}</p>
                    <ul class="mt-6 space-y-2.5">
                        @foreach ($features as $feature)
                            <li class="flex items-center gap-2.5 text-[14px] font-medium text-ink-700">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="[direction:ltr]" data-reveal="{{ $index % 2 ? 'left' : 'right' }}" data-reveal-delay="90">
                    <div class="media-zoom lift card overflow-hidden">
                        <img src="{{ asset('images/'.$image) }}" loading="lazy" alt="{{ $alt }}" class="w-full">
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

<section class="bg-ink-900">
    <div class="container-page flex flex-col items-start justify-between gap-6 py-14 lg:flex-row lg:items-center">
        <h2 class="text-[26px] font-extrabold text-white">먼저 리포트 한 장을 만들어 보세요</h2>
        <a href="{{ route('register') }}" class="btn bg-brand-500 px-7 py-3 text-[15px] text-white hover:bg-brand-600">무료로 시작하기</a>
    </div>
</section>
@endsection
