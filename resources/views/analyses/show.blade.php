@extends('layouts.app')

@php
    use App\Support\Taxonomy;

    $meta = $report['meta'] ?? [];
    $summary = $report['summary'] ?? [];

    // 이 범위·기간에 실제로 수록된 통계. 예전에 만든 리포트에는 없으므로 없으면 모두 수록으로 본다.
    $coverage = $meta['coverage'] ?? [];
    $covered = fn (string $key) => ($coverage[$key] ?? true) === true;
    // 범위 중 실제로 수록된 면적 비중. 서울·경기가 함께 걸리면 1 보다 작아진다.
    $ratio = fn (string $key) => (float) ($meta['coverage_ratio'][$key] ?? 1);
    $noSource = ($meta['sido_name'] ?? '이 지역').' 은(는) 아직 이 항목을 행정동 단위로 공개하는 출처를 확보하지 못했습니다.';

    $money = fn ($amount) => $amount >= 100000000
        ? number_format($amount / 100000000, 1).'억'
        : ($amount >= 10000 ? number_format($amount / 10000).'만' : number_format($amount));
@endphp

@section('title', $analysis->title)
@section('heading', $analysis->title)
@section('subheading', ($meta['scope_label'] ?? $analysis->rangeLabel()).' · 기준 '.($meta['base_label'] ?? '-').' · 생성 '.($meta['generated_at'] ?? '-'))

@section('actions')
    @if ($analysis->isCompleted())
        <a href="{{ route('analyses.pdf', $analysis) }}" class="btn-dark btn-sm">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v10m0 0 4-4m-4 4-4-4M5 19h14"/>
            </svg>
            PDF 내려받기
        </a>
    @endif
    <form method="POST" action="{{ route('analyses.rerun', $analysis) }}">
        @csrf
        <button class="btn-ghost btn-sm">최신 데이터로 재분석</button>
    </form>
@endsection

@section('content')

@if (! $analysis->isCompleted())
    <div class="card-pad">
        <p class="text-[16px] font-extrabold text-ink-900">
            {{ $analysis->status === 'failed' ? '분석에 실패했습니다' : '분석을 준비하고 있습니다' }}
        </p>
        <p class="mt-2 text-[14px] leading-relaxed text-ink-500">
            {{ $analysis->error_message ?? '잠시 후 다시 시도해 주세요.' }}
        </p>
        <a href="{{ route('analyses.create') }}" class="btn-primary btn-sm mt-5">다시 분석하기</a>
    </div>
@else

<div class="space-y-6">

    {{-- ─── 분석 범위 ────────────────────────────────────────── --}}
    <section class="card-pad">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow">Analysis scope</p>
                <h2 class="mt-2 text-[20px] font-extrabold text-ink-900">분석 범위</h2>
                <p class="mt-1.5 text-[14px] text-ink-500">
                    {{ $meta['scope_label'] }}
                    @if ($meta['address']) · {{ $meta['address'] }} @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="chip">{{ $meta['sido_name'] }} {{ $meta['sigungu_name'] }}</span>
                <span class="chip">기준 {{ $meta['base_label'] }}</span>
                <span class="chip">행정동 {{ count($meta['regions']) }}곳</span>
            </div>
        </div>

        {{-- 서버에서 그린 지도. PDF 와 같은 그림이라 화면과 종이가 어긋나지 않는다. --}}
        <img src="{{ route('analyses.map', $analysis) }}" alt="분석 범위 지도" loading="lazy"
             class="mt-5 w-full rounded-xl border border-line-soft"
             onerror="this.remove()">

        <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($meta['regions'] as $region)
                <li class="rounded-xl border border-line-soft bg-surface-muted px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="truncate text-[13px] font-semibold text-ink-700">{{ $region['name'] }}</span>
                        <span class="shrink-0 text-[12px] font-bold tabular-nums text-brand-600">
                            {{ round($region['weight'] * 100) }}%
                        </span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ round($region['weight'] * 100) }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($analysis->mode === 'radius')
            <p class="mt-4 text-[12px] leading-relaxed text-ink-400">
                * 퍼센트는 해당 행정동 면적 중 분석 반경에 포함된 비율입니다. 모든 통계는 이 비율만큼 안분해 합산했습니다.
            </p>
        @endif
    </section>

    {{-- ─── 인구 요약 ────────────────────────────────────────── --}}
    <section class="card-pad">
        <p class="eyebrow">01</p>
        <h2 class="mt-2 text-[20px] font-extrabold text-ink-900">인구 요약</h2>

        @php
            $summaryMetrics = [
                'resident' => '거주 인구(추정)',
                'households' => '배후세대',
                'lunch_floating' => '점심 유동인구(일평균)',
                'evening_floating' => '저녁 유동인구(일평균)',
                'workplace' => '직장인구',
            ];
        @endphp

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($summaryMetrics as $key => $label)
                @php $hasMetric = ($summary['coverage'][$key] ?? true) === true; @endphp
                <div class="rounded-xl border border-line-soft p-4">
                    <p class="text-[12px] font-semibold text-ink-400">{{ $label }}</p>
                    @if ($hasMetric)
                        <p class="stat-value mt-1.5">{{ number_format($summary['selected'][$key]) }}</p>
                        <p class="mt-2 inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-bold text-brand-600">
                            {{ $meta['sido_name'] }} 평균 대비 {{ $summary['levels'][$key] }}
                        </p>
                    @else
                        <p class="stat-value mt-1.5 text-ink-300">—</p>
                        <p class="mt-2 inline-flex rounded-full bg-ink-50 px-2.5 py-1 text-[11px] font-bold text-ink-400">
                            미수록
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        @php
            // 비교 그래프·표에는 수록된 항목만 올린다. 미수록을 0 으로 그리면 비교가 거짓말이 된다.
            $summaryMetrics = array_filter(
                $summaryMetrics,
                fn ($key) => ($summary['coverage'][$key] ?? true) === true,
                ARRAY_FILTER_USE_KEY
            );
        @endphp

        @if ($summaryMetrics)
        <div class="mt-6 report-split lg:grid-cols-[1.3fr_1fr]">
            <div class="rounded-xl border border-line-soft p-4">
                <div class="h-[280px]">
                    @php
                        $keys = array_keys($summaryMetrics);
                        $compareConfig = [
                            'labels' => array_values($summaryMetrics),
                            'datasets' => [
                                ['label' => '선택지역', 'data' => array_map(fn ($k) => $summary['selected'][$k], $keys), 'color' => '#0593ff'],
                                ['label' => $meta['sido_name'].' 평균', 'data' => array_map(fn ($k) => round($summary['sido'][$k] ?? 0), $keys), 'color' => '#a4acbb'],
                                ['label' => $meta['sigungu_name'].' 평균', 'data' => array_map(fn ($k) => round($summary['sigungu'][$k] ?? 0), $keys), 'color' => '#c8d3e0'],
                            ],
                        ];
                    @endphp
                    <canvas data-chart="bar" data-chart-config='@json($compareConfig)'></canvas>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table-report">
                    <thead>
                        <tr>
                            <th>지역명</th>
                            @foreach ($summaryMetrics as $label)
                                <th class="!text-right">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-brand-50/50">
                            <td class="font-extrabold text-ink-900">선택지역</td>
                            @foreach (array_keys($summaryMetrics) as $key)
                                <td class="num font-extrabold text-ink-900">{{ number_format($summary['selected'][$key]) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="font-semibold">{{ $meta['sido_name'] }} 평균</td>
                            @foreach (array_keys($summaryMetrics) as $key)
                                <td class="num">{{ number_format($summary['sido'][$key] ?? 0) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="font-semibold">{{ $meta['sigungu_name'] }} 평균</td>
                            @foreach (array_keys($summaryMetrics) as $key)
                                <td class="num">{{ number_format($summary['sigungu'][$key] ?? 0) }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
                <p class="mt-2 text-[12px] leading-relaxed text-ink-300">
                    * 상위 시도/시군구 평균은 그 지역에 속한 행정동 1곳당 평균값입니다.
                </p>
            </div>
        </div>
        @endif

        @include('analyses.partials.insights', ['lines' => $summary['insights'] ?? []])
    </section>

    {{-- ─── 인구 상세 ────────────────────────────────────────── --}}
    <section class="card-pad">
        <p class="eyebrow">02</p>
        <h2 class="mt-2 text-[20px] font-extrabold text-ink-900">인구 상세분석</h2>

        <div class="mt-7">
            <h3 class="text-[15px] font-extrabold text-ink-900">거주 인구(추정)</h3>
            <p class="mt-1 text-[12px] text-ink-400">
                * 주민등록인구 수와 배후세대 분포를 활용해 해당 지역에 거주하는 인구를 추정한 값입니다.
            </p>
            @if ($covered('resident'))
                <div class="mt-4">
                    @include('analyses.partials.gender-age', [
                        'data' => $report['resident'],
                        'chartId' => 'chart-resident',
                        'unit' => '명',
                    ])
                </div>
            @else
                @include('analyses.partials.uncovered', ['reason' => $noSource])
            @endif
            @include('analyses.partials.partial-coverage', ['ratio' => $ratio('resident')])
        </div>

        {{-- 배후세대 --}}
        <div class="mt-10 border-t border-line-soft pt-8">
            <h3 class="text-[15px] font-extrabold text-ink-900">배후세대</h3>

            @if (! $covered('households'))
                @include('analyses.partials.uncovered', ['reason' => $noSource])
            @else
            <div class="mt-4 report-split lg:grid-cols-[1.35fr_1fr]">
                <div class="rounded-xl border border-line-soft p-4">
                    <div class="h-[240px]">
                        @php
                            $housingConfig = [
                                'labels' => array_values(Taxonomy::HOUSING_LABELS),
                                'datasets' => [[
                                    'label' => '세대 수',
                                    'data' => array_map(fn ($t) => $report['households']['by_type'][$t] ?? 0, Taxonomy::HOUSING_TYPES),
                                    'color' => '#0593ff',
                                ]],
                            ];
                        @endphp
                        <canvas data-chart="bar" data-chart-config='@json($housingConfig)'></canvas>
                    </div>
                </div>

                <div>
                    <table class="table-report">
                        <thead>
                            <tr>
                                <th>거주유형</th>
                                <th class="!text-right">세대 수</th>
                                <th class="!text-right">비중</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (Taxonomy::HOUSING_TYPES as $type)
                                @php $value = $report['households']['by_type'][$type] ?? 0; @endphp
                                <tr>
                                    <td class="font-semibold text-ink-900">{{ Taxonomy::HOUSING_LABELS[$type] }}</td>
                                    <td class="num">{{ number_format($value) }}</td>
                                    <td class="num text-ink-400">
                                        {{ $report['households']['total'] > 0 ? number_format($value / $report['households']['total'] * 100, 1) : '0.0' }}%
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-surface-muted">
                                <td class="font-extrabold text-ink-900">총합</td>
                                <td class="num font-extrabold text-ink-900">{{ number_format($report['households']['total']) }}</td>
                                <td class="num font-extrabold text-ink-900">100.0%</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-right text-[12px] text-ink-300">단위 : 세대</p>
                </div>
            </div>

            <div class="mt-7">
                <h4 class="text-[14px] font-extrabold text-ink-900">아파트 입주 예정 세대 (3년 이내)</h4>
                @if (empty($report['households']['move_ins']))
                    <p class="mt-3 rounded-xl border border-dashed border-line px-4 py-5 text-center text-[13px] text-ink-400">
                        3년 이내 입주예정인 단지가 조회되지 않습니다.
                    </p>
                @else
                    <table class="table-report mt-3">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>단지명</th>
                                <th class="!text-right">세대 수</th>
                                <th class="!text-right">입주년월</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['households']['move_ins'] as $index => $moveIn)
                                <tr>
                                    <td class="text-ink-300">{{ $index + 1 }}</td>
                                    <td class="font-semibold text-ink-900">{{ $moveIn['complex_name'] }}</td>
                                    <td class="num">{{ number_format($moveIn['households']) }}</td>
                                    <td class="num">{{ substr($moveIn['move_in_ym'], 0, 4) }}.{{ substr($moveIn['move_in_ym'], 4, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @endif
        </div>

        {{-- 직장인구 --}}
        <div class="mt-10 border-t border-line-soft pt-8">
            <h3 class="text-[15px] font-extrabold text-ink-900">직장인구</h3>
            <p class="mt-1 text-[12px] text-ink-400">
                * 사업체 조사를 기반으로 주거건물을 제외한 건물의 면적과 층수를 고려해 산정한 값입니다.
            </p>
            @if ($covered('workplace'))
                <div class="mt-4">
                    @include('analyses.partials.gender-age', [
                        'data' => $report['workplace'],
                        'ageBands' => Taxonomy::WORK_AGE_BANDS,
                        'chartId' => 'chart-workplace',
                        'unit' => '명',
                    ])
                </div>
            @else
                @include('analyses.partials.uncovered', ['reason' => $noSource])
            @endif
            @include('analyses.partials.partial-coverage', ['ratio' => $ratio('workplace')])
        </div>

        {{-- 유동인구 --}}
        <div class="mt-10 border-t border-line-soft pt-8">
            <h3 class="text-[15px] font-extrabold text-ink-900">유동인구</h3>
            <p class="mt-1 text-[12px] text-ink-400">
                * 하루 평균값입니다. 평일·주말은 각각 해당 요일 수로 나눠 계산했습니다.<br>
                * 오전 6:00-10:59 | 점심 11:00-14:59 | 오후 15:00-17:59 | 저녁 18:00-20:59 | 밤 21:00-05:59
            </p>

            @if (! $covered('floating'))
                @include('analyses.partials.uncovered', ['reason' => $noSource])
            @else
            <div class="mt-4 rounded-xl border border-line-soft p-4">
                <div class="h-[280px]">
                    @php
                        $floatingConfig = [
                            'labels' => array_values(Taxonomy::TIME_BAND_LABELS),
                            'datasets' => [
                                ['label' => '평일', 'data' => array_map(fn ($b) => $report['floating']['by_day_band']['weekday'][$b] ?? 0, Taxonomy::TIME_BANDS), 'color' => '#0593ff'],
                                ['label' => '주말', 'data' => array_map(fn ($b) => $report['floating']['by_day_band']['weekend'][$b] ?? 0, Taxonomy::TIME_BANDS), 'color' => '#09e092'],
                            ],
                        ];
                    @endphp
                    <canvas data-chart="line" data-chart-config='@json($floatingConfig)'></canvas>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="table-report">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach (Taxonomy::TIME_BANDS as $band)
                                <th class="!text-right">{{ Taxonomy::TIME_BAND_LABELS[$band] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (Taxonomy::DAY_TYPES as $dayType)
                            <tr>
                                <td class="font-extrabold text-ink-900">{{ Taxonomy::DAY_TYPE_LABELS[$dayType] }}</td>
                                @foreach (Taxonomy::TIME_BANDS as $band)
                                    <td class="num">{{ number_format($report['floating']['by_day_band'][$dayType][$band] ?? 0) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-2 text-right text-[12px] text-ink-300">단위 : 명</p>
            </div>

            <div class="mt-7">
                <h4 class="text-[14px] font-extrabold text-ink-900">평일 유동인구 성 · 연령 구성</h4>
                <div class="mt-4">
                    @include('analyses.partials.gender-age', [
                        'data' => $report['floating']['by_gender_age'],
                        'chartId' => 'chart-floating-demo',
                        'unit' => '명',
                    ])
                </div>
            </div>
            @endif
            @include('analyses.partials.partial-coverage', ['ratio' => $ratio('floating')])
        </div>
    </section>

    {{-- ─── 카드매출 ─────────────────────────────────────────── --}}
    <section class="card-pad">
        <p class="eyebrow">03</p>
        <h2 class="mt-2 text-[20px] font-extrabold text-ink-900">카드매출 분석</h2>

        @if (! $covered('sales'))
            @include('analyses.partials.uncovered', ['reason' => $noSource])
        @else
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['일평균 카드매출', $money($report['sales']['total_amount']).'원'],
                ['일평균 결제 건수', number_format($report['sales']['total_count']).'건'],
                ['건당 평균 결제', number_format($report['sales']['avg_ticket']).'원'],
            ] as [$label, $value])
                <div class="rounded-xl border border-line-soft p-4">
                    <p class="text-[12px] font-semibold text-ink-400">{{ $label }}</p>
                    <p class="stat-value mt-1.5">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-7 report-split lg:grid-cols-[1fr_1fr]">
            <div>
                <h3 class="text-[15px] font-extrabold text-ink-900">업종별 매출</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="table-report">
                        <thead>
                            <tr>
                                <th>업종</th>
                                <th>대분류</th>
                                <th class="!text-right">매출액</th>
                                <th class="!text-right">비중</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['sales']['by_industry'] as $industry)
                                <tr>
                                    <td class="font-semibold text-ink-900">{{ $industry['name'] }}</td>
                                    <td class="text-ink-400">{{ $industry['group'] }}</td>
                                    <td class="num">{{ $money($industry['amount']) }}원</td>
                                    <td class="num text-ink-400">{{ number_format($industry['share'], 1) }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-ink-400">카드매출 데이터가 없습니다.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-line-soft p-4">
                    <p class="text-[13px] font-extrabold text-ink-900">업종 대분류 비중</p>
                    <div class="mt-3 h-[220px]">
                        @php
                            $groupConfig = [
                                'labels' => array_column($report['sales']['by_group'], 'name'),
                                'datasets' => [['label' => '매출액', 'data' => array_column($report['sales']['by_group'], 'amount')]],
                            ];
                        @endphp
                        <canvas data-chart="doughnut" data-chart-money="1" data-chart-config='@json($groupConfig)'></canvas>
                    </div>
                </div>

                <div class="rounded-xl border border-line-soft p-4">
                    <p class="text-[13px] font-extrabold text-ink-900">요일 · 시간대별 매출</p>
                    <div class="mt-3 h-[220px]">
                        @php
                            $salesBandConfig = [
                                'labels' => array_values(Taxonomy::TIME_BAND_LABELS),
                                'datasets' => [
                                    ['label' => '평일', 'data' => array_map(fn ($b) => $report['sales']['by_day_band']['weekday'][$b] ?? 0, Taxonomy::TIME_BANDS), 'color' => '#0593ff'],
                                    ['label' => '주말', 'data' => array_map(fn ($b) => $report['sales']['by_day_band']['weekend'][$b] ?? 0, Taxonomy::TIME_BANDS), 'color' => '#09e092'],
                                ],
                            ];
                        @endphp
                        <canvas data-chart="bar" data-chart-money="1" data-chart-config='@json($salesBandConfig)'></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 border-t border-line-soft pt-7">
            <h3 class="text-[15px] font-extrabold text-ink-900">성 · 연령별 매출</h3>
            <div class="mt-4">
                @include('analyses.partials.gender-age', [
                    'data' => $report['sales']['by_gender_age'],
                    'chartId' => 'chart-sales-demo',
                    'unit' => '원',
                    'asMoney' => true,
                ])
            </div>
        </div>

        @include('analyses.partials.insights', ['lines' => $report['sales']['insights'] ?? []])
        @endif
        @include('analyses.partials.partial-coverage', ['ratio' => $ratio('sales')])
    </section>

    {{-- ─── 업종 분야 · 프랜차이즈 ───────────────────────────── --}}
    <section class="card-pad">
        <p class="eyebrow">04</p>
        <h2 class="mt-2 text-[20px] font-extrabold text-ink-900">업종 분야 · 프랜차이즈</h2>
        <p class="mt-1 text-[12px] text-ink-400">
            * 소상공인시장진흥공단 상가(상권)정보의 실제 점포를 분야와 브랜드로 분류한 값입니다.
            반경 분석은 겹치는 면적 비율을 적용합니다.
        </p>

        @if (! $covered('stores'))
            @include('analyses.partials.uncovered', [
                'reason' => '이 범위의 점포 정보를 아직 수집하지 않았습니다. php artisan sbiz:sync-stores 로 수집할 수 있습니다.',
            ])
        @else
            @php $stores = $report['stores']; @endphp

            <div class="mt-5 grid gap-3 sm:grid-cols-4">
                @foreach ([
                    ['전체 점포', number_format($stores['total']).'개'],
                    ['프랜차이즈', number_format($stores['franchise_total'] ?? 0).'개 ('.number_format($stores['franchise_share'] ?? 0, 1).'%)'],
                    ['다점포 상호', number_format($stores['chain_total'] ?? 0).'개 ('.number_format($stores['chain_share'] ?? 0, 1).'%)'],
                    ['최다 분야', ($stores['by_sector'][0]['name'] ?? '-')],
                ] as [$label, $value])
                    <div class="rounded-xl border border-line-soft p-4">
                        <p class="text-[12px] font-semibold text-ink-400">{{ $label }}</p>
                        <p class="stat-value mt-1.5">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            {{-- 분야별 구성 --}}
            <div class="mt-7 report-split lg:grid-cols-2">
                <div class="min-w-0">
                    <h3 class="text-[15px] font-extrabold text-ink-900">분야별 점포</h3>
                    <div class="mt-4 rounded-xl border border-line-soft p-4">
                        <div class="h-[440px]">
                            @php
                                $sectorConfig = [
                                    'labels' => array_column($stores['by_sector'], 'name'),
                                    'datasets' => [[
                                        'label' => '점포 수',
                                        'data' => array_column($stores['by_sector'], 'count'),
                                        'color' => '#0593ff',
                                    ]],
                                ];
                            @endphp
                            <canvas data-chart="hbar" data-chart-config='@json($sectorConfig)'></canvas>
                        </div>
                    </div>
                </div>

                <div class="min-w-0">
                    <h3 class="text-[15px] font-extrabold text-ink-900">분야별 프랜차이즈 비중</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="table-report">
                            <thead>
                                <tr>
                                    <th>분야</th>
                                    <th class="!text-right">점포</th>
                                    <th class="!text-right">비중</th>
                                    <th class="!text-right">프랜차이즈</th>
                                    <th class="!text-right">프랜차이즈율</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stores['by_sector'] as $sector)
                                    <tr>
                                        <td class="font-semibold text-ink-900">{{ $sector['name'] }}</td>
                                        <td class="num">{{ number_format($sector['count']) }}</td>
                                        <td class="num text-ink-400">{{ number_format($sector['share'], 1) }}%</td>
                                        <td class="num">{{ number_format($sector['franchises'] ?? 0) }}</td>
                                        <td class="num font-bold text-brand-600">
                                            {{ number_format($sector['franchise_share'] ?? 0, 1) }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 프랜차이즈 브랜드 --}}
            <div class="mt-10 border-t border-line-soft pt-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-[15px] font-extrabold text-ink-900">프랜차이즈 브랜드</h3>
                        <p class="mt-1 text-[12px] leading-relaxed text-ink-400">
                            분야별로 매장 수가 많은 브랜드입니다. 전체 목록은 CSV 로 내려받을 수 있습니다.<br>
                            이름이 확인된 프랜차이즈만 싣습니다. 사전에 없는 상호를 반복 출현으로 찾은
                            <strong>다점포 상호 {{ number_format($stores['chain_total'] ?? 0) }}개</strong>는
                            이름을 믿기 어려워 개수로만 표시합니다.
                        </p>
                    </div>
                    <a href="{{ route('analyses.franchises', $analysis) }}" class="btn-ghost btn-sm">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v10m0 0 4-4m-4 4-4-4M5 19h14"/>
                        </svg>
                        브랜드 목록 CSV
                    </a>
                </div>

                @if (empty($stores['brands']))
                    <p class="mt-4 rounded-xl border border-dashed border-line px-4 py-6 text-center text-[13px] leading-relaxed text-ink-400">
                        이 범위에서 이름이 확인된 프랜차이즈가 없습니다.<br>
                        등록된 브랜드 사전에 없는 지역 브랜드일 수 있습니다.
                    </p>
                @else
                    <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($stores['by_sector'] as $sector)
                            @php $sectorBrands = $stores['brands_by_sector'][$sector['code']] ?? []; @endphp
                            @continue(empty($sectorBrands))
                            <div class="rounded-xl border border-line-soft p-4">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="text-[13px] font-extrabold text-ink-900">{{ $sector['name'] }}</p>
                                    <p class="text-[11px] font-semibold text-ink-400">브랜드 {{ count($sectorBrands) }}</p>
                                </div>
                                <ul class="mt-3 space-y-2">
                                    @foreach ($sectorBrands as $brand)
                                        <li>
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="truncate text-[13px] font-semibold text-ink-700">{{ $brand['name'] }}</span>
                                                <span class="shrink-0 text-[12px] font-bold tabular-nums text-brand-600">
                                                    {{ number_format($brand['count']) }}개
                                                </span>
                                            </div>
                                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                                                <div class="h-full rounded-full bg-brand-500"
                                                     style="width: {{ max(4, round($brand['count'] / max(1, $sectorBrands[0]['count']) * 100)) }}%"></div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 세부 업종 --}}
            <div class="mt-10 border-t border-line-soft pt-8">
                <h3 class="text-[15px] font-extrabold text-ink-900">세부 업종 상위</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="table-report">
                        <thead>
                            <tr>
                                <th>업종</th>
                                <th>대분류</th>
                                <th class="!text-right">점포 수</th>
                                <th class="!text-right">비중</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stores['by_middle'] as $store)
                                <tr>
                                    <td class="font-semibold text-ink-900">{{ $store['name'] }}</td>
                                    <td class="text-ink-400">{{ $store['large'] }}</td>
                                    <td class="num">{{ number_format($store['count']) }}</td>
                                    <td class="num text-ink-400">{{ number_format($store['share'], 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('analyses.partials.insights', ['lines' => $stores['insights'] ?? []])
        @endif
    </section>

    {{-- ─── 학생 · 학원 ──────────────────────────────────────── --}}
    <section class="card-pad">
        <p class="eyebrow">05</p>
        <h2 class="mt-2 text-[20px] font-extrabold text-ink-900">학생 수 분석</h2>

        <div class="mt-5 report-split lg:grid-cols-[1.35fr_1fr]">
            <div class="rounded-xl border border-line-soft p-4">
                <div class="h-[240px]">
                    @php
                        $studentConfig = [
                            'labels' => array_values(Taxonomy::SCHOOL_LABELS),
                            'datasets' => [[
                                'label' => '학생 수',
                                'data' => array_map(fn ($t) => $report['education']['students']['by_type'][$t] ?? 0, Taxonomy::SCHOOL_TYPES),
                                'color' => '#0593ff',
                            ]],
                        ];
                    @endphp
                    <canvas data-chart="bar" data-chart-config='@json($studentConfig)'></canvas>
                </div>
            </div>

            <div>
                <table class="table-report">
                    <thead><tr><th>구분</th><th class="!text-right">학생 수</th></tr></thead>
                    <tbody>
                        @foreach (Taxonomy::SCHOOL_TYPES as $type)
                            @php $value = $report['education']['students']['by_type'][$type] ?? 0; @endphp
                            <tr>
                                <td class="font-semibold text-ink-900">{{ Taxonomy::SCHOOL_LABELS[$type] }}</td>
                                <td class="num">{{ $value > 0 ? number_format($value) : '-' }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-surface-muted">
                            <td class="font-extrabold text-ink-900">총합</td>
                            <td class="num font-extrabold text-ink-900">{{ number_format($report['education']['students']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-2 text-right text-[12px] text-ink-300">단위 : 명</p>
            </div>
        </div>

        <div class="mt-9 border-t border-line-soft pt-7">
            <h3 class="text-[15px] font-extrabold text-ink-900">학원 수</h3>

            <div class="mt-4 report-split lg:grid-cols-[1fr_1.2fr]">
                <div>
                    <table class="table-report">
                        <thead><tr><th>구분</th><th class="!text-right">학원 수</th></tr></thead>
                        <tbody>
                            @foreach (Taxonomy::ACADEMY_CATEGORIES as $key => $label)
                                <tr>
                                    <td class="font-semibold text-ink-900">{{ $label }}</td>
                                    <td class="num">{{ number_format($report['education']['academies']['by_category'][$key] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-surface-muted">
                                <td class="font-extrabold text-ink-900">총합</td>
                                <td class="num font-extrabold text-ink-900">{{ number_format($report['education']['academies']['total']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-right text-[12px] text-ink-300">단위 : 개</p>
                </div>

                <div>
                    <table class="table-report">
                        <thead><tr><th>업종명</th><th>구분</th><th class="!text-right">학원 수</th></tr></thead>
                        <tbody>
                            @forelse ($report['education']['academies']['by_industry'] as $academy)
                                <tr>
                                    <td class="font-semibold text-ink-900">{{ $academy['name'] }}</td>
                                    <td class="text-ink-400">{{ Taxonomy::ACADEMY_CATEGORIES[$academy['category']] ?? '-' }}</td>
                                    <td class="num">{{ number_format($academy['count']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-ink-400">학원 데이터가 없습니다.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('analyses.partials.insights', ['lines' => $report['education']['insights'] ?? []])
    </section>

    {{-- ─── 데이터 출처 ──────────────────────────────────────── --}}
    <section class="card-pad">
        <p class="eyebrow">06</p>
        <h2 class="mt-2 text-[20px] font-extrabold text-ink-900">데이터 출처</h2>

        <table class="table-report mt-5">
            <thead>
                <tr><th>데이터</th><th>출처</th><th class="!text-right">데이터 기준월</th></tr>
            </thead>
            <tbody>
                @foreach ($report['sources'] as $source)
                    <tr>
                        <td class="font-semibold text-ink-900">{{ $source['label'] }}</td>
                        <td>{{ $source['provider'] }}</td>
                        <td class="num">{{ $source['base_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="mt-4 text-[12px] leading-relaxed text-ink-400">
            * 보고서의 모든 정보는 집계 방법과 기준일, 분석 방법에 따라 오차가 발생할 수 있으니 참고용으로 활용해 주세요.<br>
            &nbsp;&nbsp;서면 제공 시에는 가맹사업법에서 정한 양식과 기준에 따라 작성해 주시기 바랍니다.
        </p>
    </section>
</div>

@endif
@endsection
