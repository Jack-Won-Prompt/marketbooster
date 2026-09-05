@extends('layouts.site')

@section('title', '솔루션')

@section('content')
<section class="border-b border-line-soft bg-gradient-to-b from-brand-50/70 to-white">
    <div class="container-page py-20 lg:py-24">
        <p class="eyebrow">Solutions</p>
        <h1 class="section-title mt-4 max-w-3xl">지역만 고르면, 나머지는 플랫폼이 합니다</h1>
        <p class="mt-5 max-w-2xl text-[16px] leading-relaxed text-ink-500">
            흩어진 공공데이터를 행정동·기준연월이라는 같은 축으로 정규화해 두었기 때문에,
            반경 하나만 정해도 인구·매출·교육 지표가 한 번에 계산됩니다.
        </p>
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
            ],
            [
                'Map',
                '반경 · 행정동 선택',
                '지도에서 중심을 찍고 반경을 정하면 원에 걸친 행정동을 찾아냅니다. 행정동 경계 폴리곤에 격자점을 뿌려 실제로 겹친 면적 비율을 구하고, 그 비율만큼 통계를 안분합니다.',
                ['반경 300m ~ 3km', '행정동 직접 선택 (최대 30곳)', '겹침 비율 미리보기', '관심지역 저장'],
            ],
            [
                'Compare',
                '상위 지역 대비 비교',
                '같은 시도와 시군구에 속한 행정동 1곳당 평균을 자동으로 계산해 기준선으로 씁니다. 절대 수치만으로는 알기 어려운 "이 상권이 큰 편인지"를 바로 판단할 수 있습니다.',
                ['시도 평균 비교', '시군구 평균 비교', '매우 높음 ~ 매우 낮음 5단계 판정'],
            ],
            [
                'Data API',
                '수집 파이프라인',
                '공공데이터포털 오픈 API와 파일데이터(CSV)를 같은 스키마로 적재합니다. 기관마다 다른 필드명·코드값은 정규화 사전을 거쳐 내부 표준으로 변환되고, 재수집하면 같은 기준월 데이터가 갱신됩니다.',
                ['REST API 수집', 'CP949 CSV 적재', '필드명/코드 자동 매핑', '수집 이력 로그'],
            ],
        ] as $index => [$tag, $title, $desc, $features])
            <div class="grid items-start gap-10 lg:grid-cols-2 {{ $index % 2 ? 'lg:[direction:rtl]' : '' }}">
                <div class="[direction:ltr]">
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

                <div class="card p-8 [direction:ltr]">
                    <div class="rounded-xl bg-surface-muted p-6">
                        <p class="text-[12px] font-bold text-ink-400">{{ $tag }} preview</p>
                        <div class="mt-4 space-y-2.5">
                            @foreach ([88, 64, 42, 30] as $width)
                                <div class="h-2.5 rounded-full bg-brand-500/70" style="width: {{ $width }}%"></div>
                            @endforeach
                        </div>
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
