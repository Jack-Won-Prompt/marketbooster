@php
    use App\Support\Taxonomy;

    $meta = $report['meta'];
    $summary = $report['summary'];
    $money = fn ($amount) => $amount >= 100000000
        ? number_format($amount / 100000000, 1).'억'
        : ($amount >= 10000 ? number_format($amount / 10000).'만' : number_format($amount));

    $summaryMetrics = [
        'resident' => '거주 인구(추정)',
        'households' => '배후세대',
        'lunch_floating' => '점심* 유동인구',
        'evening_floating' => '저녁* 유동인구',
        'workplace' => '직장인구',
    ];
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <title>{{ $analysis->title }} 상권분석 보고서</title>
    <style>
        /* 한글 글꼴 등록 — dompdf 는 시스템 글꼴을 모르므로 파일 경로로 직접 지정한다. */
        @font-face {
            font-family: 'pretendard';
            font-style: normal;
            font-weight: 400;
            src: url('{{ str_replace('\\', '/', storage_path('fonts/Pretendard-Regular.ttf')) }}') format('truetype');
        }
        @font-face {
            font-family: 'pretendard';
            font-style: normal;
            font-weight: 700;
            src: url('{{ str_replace('\\', '/', storage_path('fonts/Pretendard-Bold.ttf')) }}') format('truetype');
        }

        @page { margin: 26mm 14mm 20mm 14mm; }

        * { font-family: 'pretendard', sans-serif; }

        body { margin: 0; color: #100f14; font-size: 9.5pt; line-height: 1.55; }

        /* 머리말 / 꼬리말 */
        header {
            position: fixed; top: -16mm; left: 0; right: 0; height: 9mm;
            background-color: #eef2f7; color: #5a6274; font-size: 7.5pt;
            padding: 2.4mm 3mm 0 3mm;
        }
        header .right { float: right; }

        footer {
            position: fixed; bottom: -14mm; left: 0; right: 0;
            text-align: center; color: #7b8394; font-size: 8pt;
        }
        footer .page:after { content: counter(page); }

        .page-break { page-break-after: always; }

        h1.cover-title { font-size: 30pt; color: #00599d; margin: 0 0 6mm 0; letter-spacing: -0.5pt; }
        h2 { font-size: 16pt; color: #00599d; margin: 0 0 4mm 0; }
        h3 { font-size: 11pt; margin: 7mm 0 2.5mm 0; }
        h4 { font-size: 9.5pt; margin: 5mm 0 2mm 0; }

        .muted { color: #7b8394; font-size: 7.5pt; line-height: 1.5; }
        .unit { text-align: right; color: #7b8394; font-size: 7.5pt; margin-bottom: 1mm; }

        table { width: 100%; border-collapse: collapse; }

        table.data th {
            background-color: #f6f8fa; border-top: 0.6pt solid #5a6274; border-bottom: 0.6pt solid #d8e1ef;
            padding: 2mm 2.4mm; font-size: 8pt; color: #2c303a; text-align: left;
        }
        table.data td { border-bottom: 0.5pt solid #eef2f9; padding: 2mm 2.4mm; font-size: 8.5pt; }
        table.data td.num, table.data th.num { text-align: right; }
        table.data tr.total td { background-color: #f6f8fa; font-weight: bold; }
        table.data tr.highlight td { background-color: #eef4ff; font-weight: bold; }

        /* 요약 카드 */
        table.cards td {
            width: 20%; border: 0.5pt solid #eef2f9; padding: 3mm 2.5mm; vertical-align: top;
        }
        table.cards .card-label { color: #7b8394; font-size: 7.5pt; }
        table.cards .card-value { font-size: 14pt; font-weight: bold; margin-top: 1mm; }
        table.cards .card-level { color: #0e6ae0; font-size: 7pt; margin-top: 1mm; }

        /* 가로 막대 차트 */
        table.chart { margin: 2mm 0 1mm 0; }
        table.chart > tr > td, table.chart td { padding: 0.5mm 0; vertical-align: middle; }
        td.chart-label { width: 22mm; font-size: 8pt; color: #2c303a; }
        td.chart-track { width: auto; }
        table.chart-inner { width: 100%; }
        td.chart-bar-cell { width: 82%; padding: 0.4mm 0; }
        .chart-bar { height: 2.8mm; border-radius: 1mm; }
        td.chart-value { width: 18%; font-size: 7.5pt; color: #5a6274; padding-left: 1.6mm; text-align: left; }

        .insight {
            background-color: #f6f8fa; border-left: 1mm solid #0593ff;
            padding: 3mm 3.5mm; margin-top: 5mm; font-size: 8.5pt; line-height: 1.7;
        }
        .insight p { margin: 0 0 1.4mm 0; }

        .scope-list td { padding: 1.2mm 0; font-size: 9pt; border-bottom: 0.4pt solid #eef2f9; }
        .scope-list td.pct { text-align: right; color: #0e6ae0; font-weight: bold; width: 18mm; }

        .legend { font-size: 7.5pt; color: #5a6274; margin-top: 1.5mm; }
        .legend .dot { display: inline-block; width: 2.4mm; height: 2.4mm; border-radius: 1.2mm; margin-right: 1mm; }
    </style>
</head>
<body>

<header>
    MarketScope 상권분석
    <span class="right">보고서 생성일 : {{ $meta['generated_at'] }}</span>
</header>

<footer><span class="page"></span></footer>

{{-- ─── 표지 ─────────────────────────────────────────────────── --}}
<div class="page-break">
    <div style="border-top: 3mm solid #00599d; margin-bottom: 12mm;"></div>

    <h1 class="cover-title">상권분석 보고서</h1>

    <table style="margin-top: 4mm;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div style="font-size: 12pt; font-weight: bold;">{{ $analysis->title }}</div>
                <div class="muted" style="margin-top: 2mm; font-size: 9pt;">
                    {{ $meta['sido_name'] }} {{ $meta['sigungu_name'] }} · {{ $meta['base_label'] }} 기준
                </div>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <div style="border-bottom: 0.6pt solid #d8e1ef; padding-bottom: 1.5mm; font-size: 8.5pt; color: #5a6274;">
                    {{ $analysis->user->company ?: 'MarketScope' }}
                </div>
                <div style="padding-top: 2mm; font-size: 9.5pt; font-weight: bold;">{{ $analysis->user->name }}</div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 22mm; background-color: #f6f8fa; padding: 6mm; border: 0.5pt solid #eef2f9;">
        <table>
            <tr>
                <td style="width: 26mm; vertical-align: top; font-weight: bold; border-bottom: 0.8pt solid #100f14; padding-bottom: 1.5mm;">
                    분석 범위
                </td>
                <td style="vertical-align: top; font-weight: bold; border-bottom: 0.8pt solid #100f14; padding-bottom: 1.5mm;">
                    {{ $meta['scope_label'] }}
                </td>
            </tr>
        </table>

        <table class="scope-list" style="margin-top: 3mm;">
            @foreach ($meta['regions'] as $region)
                <tr>
                    <td>{{ $region['name'] }}</td>
                    <td class="pct">{{ round($region['weight'] * 100) }}%</td>
                </tr>
            @endforeach
        </table>

        @if ($analysis->mode === 'radius')
            <p class="muted" style="margin-top: 3mm;">
                * 퍼센트는 해당 행정동 면적 중 분석 반경에 포함된 비율이며, 모든 통계는 이 비율만큼 안분해 합산했습니다.<br>
                * 중심 좌표 : {{ number_format($meta['center']['lat'], 5) }}, {{ number_format($meta['center']['lng'], 5) }}
            </p>
        @endif
    </div>
</div>

{{-- ─── 목차 ─────────────────────────────────────────────────── --}}
<div class="page-break">
    <h2>목차</h2>

    <table style="margin-top: 8mm;">
        @foreach ([
            ['1. 인구 요약', ['거주 인구(추정)', '배후세대', '직장인구', '유동인구']],
            ['2. 카드매출 분석', ['업종별 매출', '요일 · 시간대별 매출', '성 · 연령별 매출']],
            ['3. 학생 수 분석', ['학생 수', '학원 수']],
            ['4. 데이터 출처', []],
        ] as [$chapter, $items])
            <tr>
                <td style="padding-top: 6mm; border-bottom: 0.8pt solid #100f14; font-size: 11pt; font-weight: bold;">
                    {{ $chapter }}
                </td>
            </tr>
            @foreach ($items as $item)
                <tr><td style="padding: 1.2mm 0 0 8mm; font-size: 9pt; color: #5a6274;">{{ $item }}</td></tr>
            @endforeach
        @endforeach
    </table>
</div>

{{-- ─── 인구 요약 ────────────────────────────────────────────── --}}
<div class="page-break">
    <h2>인구 요약</h2>
    <div class="unit">단위 : 명 / 세대</div>

    <table class="cards">
        <tr>
            @foreach ($summaryMetrics as $key => $label)
                <td>
                    <div class="card-label">{{ $label }}</div>
                    <div class="card-value">{{ number_format($summary['selected'][$key]) }}</div>
                    <div class="card-level">{{ $summary['levels'][$key] }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="data" style="margin-top: 5mm;">
        <thead>
            <tr>
                <th>지역명</th>
                @foreach ($summaryMetrics as $label)
                    <th class="num">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr class="highlight">
                <td>선택지역</td>
                @foreach (array_keys($summaryMetrics) as $key)
                    <td class="num">{{ number_format($summary['selected'][$key]) }}</td>
                @endforeach
            </tr>
            <tr>
                <td>{{ $meta['sido_name'] }} 평균</td>
                @foreach (array_keys($summaryMetrics) as $key)
                    <td class="num">{{ number_format($summary['sido'][$key] ?? 0) }}</td>
                @endforeach
            </tr>
            <tr>
                <td>{{ $meta['sigungu_name'] }} 평균</td>
                @foreach (array_keys($summaryMetrics) as $key)
                    <td class="num">{{ number_format($summary['sigungu'][$key] ?? 0) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top: 2mm;">
        * 점심/저녁 유동인구 : 해당 시간대의 하루 평균 유동인구입니다. (점심 11~14시, 저녁 18~20시)<br>
        * 상위 시도/시군구 평균 : 그 지역에 속한 행정동 1곳당 평균값으로, 반경 분석 시 큰 차이가 날 수 있습니다.
    </p>

    <div class="insight">
        @foreach ($summary['insights'] as $line)
            <p>{{ $line }}</p>
        @endforeach
    </div>

    <h3>거주 인구(추정)</h3>
    <table>
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 5mm;">
                @include('reports.partials.hbar', [
                    'money' => false,
                    'rows' => collect(Taxonomy::AGE_BANDS)->map(fn ($band) => [
                        'label' => Taxonomy::AGE_LABELS[$band],
                        'bars' => [
                            ['value' => $report['resident']['matrix']['M'][$band] ?? 0, 'color' => '#0593ff'],
                            ['value' => $report['resident']['matrix']['F'][$band] ?? 0, 'color' => '#f2557b'],
                        ],
                    ])->all(),
                ])
                <div class="legend">
                    <span class="dot" style="background-color:#0593ff;"></span>남성
                    <span class="dot" style="background-color:#f2557b; margin-left:3mm;"></span>여성
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <div class="unit">단위 : 명</div>
                <table class="data">
                    <thead><tr><th>연령</th><th class="num">남성</th><th class="num">여성</th></tr></thead>
                    <tbody>
                        @foreach (Taxonomy::AGE_BANDS as $band)
                            <tr>
                                <td>{{ Taxonomy::AGE_LABELS[$band] }}</td>
                                <td class="num">{{ number_format($report['resident']['matrix']['M'][$band] ?? 0) }}</td>
                                <td class="num">{{ number_format($report['resident']['matrix']['F'][$band] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>총합</td>
                            <td class="num">{{ number_format($report['resident']['male']) }}</td>
                            <td class="num">{{ number_format($report['resident']['female']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
    <p class="muted">* 주민등록인구 수와 배후세대 분포를 활용해 해당 지역에 거주하는 인구를 추정한 정보입니다.</p>
</div>

{{-- ─── 배후세대 · 직장인구 ──────────────────────────────────── --}}
<div class="page-break">
    <h2>인구 상세분석</h2>

    <h3>배후세대</h3>
    <table>
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 5mm;">
                @include('reports.partials.hbar', [
                    'money' => false,
                    'rows' => collect(Taxonomy::HOUSING_TYPES)->map(fn ($type) => [
                        'label' => Taxonomy::HOUSING_LABELS[$type],
                        'bars' => [['value' => $report['households']['by_type'][$type] ?? 0, 'color' => '#0593ff']],
                    ])->all(),
                ])
            </td>
            <td style="width: 48%; vertical-align: top;">
                <div class="unit">단위 : 세대</div>
                <table class="data">
                    <thead><tr><th>거주유형</th><th class="num">세대 수</th><th class="num">비중</th></tr></thead>
                    <tbody>
                        @foreach (Taxonomy::HOUSING_TYPES as $type)
                            @php $value = $report['households']['by_type'][$type] ?? 0; @endphp
                            <tr>
                                <td>{{ Taxonomy::HOUSING_LABELS[$type] }}</td>
                                <td class="num">{{ number_format($value) }}</td>
                                <td class="num">{{ $report['households']['total'] > 0 ? number_format($value / $report['households']['total'] * 100, 1) : '0.0' }}%</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>총합</td>
                            <td class="num">{{ number_format($report['households']['total']) }}</td>
                            <td class="num">100.0%</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h4>아파트 입주 예정 세대 (3년 이내)</h4>
    <table class="data">
        <thead><tr><th style="width:8mm;">#</th><th>단지명</th><th class="num">세대 수</th><th class="num">입주년월</th></tr></thead>
        <tbody>
            @forelse ($report['households']['move_ins'] as $index => $moveIn)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $moveIn['complex_name'] }}</td>
                    <td class="num">{{ number_format($moveIn['households']) }}</td>
                    <td class="num">{{ substr($moveIn['move_in_ym'], 0, 4) }}.{{ substr($moveIn['move_in_ym'], 4, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="color:#7b8394;">3년 이내 입주예정인 단지가 조회되지 않습니다.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>직장인구</h3>
    <table>
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 5mm;">
                @include('reports.partials.hbar', [
                    'money' => false,
                    'rows' => collect(Taxonomy::WORK_AGE_BANDS)->map(fn ($band) => [
                        'label' => Taxonomy::AGE_LABELS[$band],
                        'bars' => [
                            ['value' => $report['workplace']['matrix']['M'][$band] ?? 0, 'color' => '#0593ff'],
                            ['value' => $report['workplace']['matrix']['F'][$band] ?? 0, 'color' => '#f2557b'],
                        ],
                    ])->all(),
                ])
                <div class="legend">
                    <span class="dot" style="background-color:#0593ff;"></span>남성
                    <span class="dot" style="background-color:#f2557b; margin-left:3mm;"></span>여성
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <div class="unit">단위 : 명</div>
                <table class="data">
                    <thead><tr><th>연령</th><th class="num">남성</th><th class="num">여성</th></tr></thead>
                    <tbody>
                        @foreach (Taxonomy::WORK_AGE_BANDS as $band)
                            <tr>
                                <td>{{ Taxonomy::AGE_LABELS[$band] }}</td>
                                <td class="num">{{ number_format($report['workplace']['matrix']['M'][$band] ?? 0) }}</td>
                                <td class="num">{{ number_format($report['workplace']['matrix']['F'][$band] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>총합</td>
                            <td class="num">{{ number_format($report['workplace']['male']) }}</td>
                            <td class="num">{{ number_format($report['workplace']['female']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
    <p class="muted">* 사업체 조사를 기반으로 주거건물을 제외한 건물의 면적과 층수를 고려해 산정한 정보입니다.</p>
</div>

{{-- ─── 유동인구 ─────────────────────────────────────────────── --}}
<div class="page-break">
    <h2>유동인구</h2>
    <div class="unit">단위 : 명</div>

    @include('reports.partials.hbar', [
        'money' => false,
        'rows' => collect(Taxonomy::TIME_BANDS)->map(fn ($band) => [
            'label' => Taxonomy::TIME_BAND_LABELS[$band],
            'bars' => [
                ['value' => $report['floating']['by_day_band']['weekday'][$band] ?? 0, 'color' => '#0593ff'],
                ['value' => $report['floating']['by_day_band']['weekend'][$band] ?? 0, 'color' => '#09e092'],
            ],
        ])->all(),
    ])
    <div class="legend">
        <span class="dot" style="background-color:#0593ff;"></span>평일
        <span class="dot" style="background-color:#09e092; margin-left:3mm;"></span>주말
    </div>

    <table class="data" style="margin-top: 5mm;">
        <thead>
            <tr>
                <th></th>
                @foreach (Taxonomy::TIME_BANDS as $band)
                    <th class="num">{{ Taxonomy::TIME_BAND_LABELS[$band] }}*</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach (Taxonomy::DAY_TYPES as $dayType)
                <tr>
                    <td style="font-weight:bold;">{{ Taxonomy::DAY_TYPE_LABELS[$dayType] }}</td>
                    @foreach (Taxonomy::TIME_BANDS as $band)
                        <td class="num">{{ number_format($report['floating']['by_day_band'][$dayType][$band] ?? 0) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="muted" style="margin-top:2mm;">
        * 하루 평균값입니다. 평일·주말은 각각 해당 요일 수로 나눠 계산했습니다.<br>
        * 오전 6:00-10:59 | 점심 11:00-14:59 | 오후 15:00-17:59 | 저녁 18:00-20:59 | 밤 21:00-05:59
    </p>

    <h3>평일 유동인구 성 · 연령 구성</h3>
    <table>
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 5mm;">
                @include('reports.partials.hbar', [
                    'money' => false,
                    'rows' => collect(Taxonomy::AGE_BANDS)->map(fn ($band) => [
                        'label' => Taxonomy::AGE_LABELS[$band],
                        'bars' => [
                            ['value' => $report['floating']['by_gender_age']['matrix']['M'][$band] ?? 0, 'color' => '#0593ff'],
                            ['value' => $report['floating']['by_gender_age']['matrix']['F'][$band] ?? 0, 'color' => '#f2557b'],
                        ],
                    ])->all(),
                ])
            </td>
            <td style="width: 48%; vertical-align: top;">
                <div class="unit">단위 : 명</div>
                <table class="data">
                    <thead><tr><th>연령</th><th class="num">남성</th><th class="num">여성</th></tr></thead>
                    <tbody>
                        @foreach (Taxonomy::AGE_BANDS as $band)
                            <tr>
                                <td>{{ Taxonomy::AGE_LABELS[$band] }}</td>
                                <td class="num">{{ number_format($report['floating']['by_gender_age']['matrix']['M'][$band] ?? 0) }}</td>
                                <td class="num">{{ number_format($report['floating']['by_gender_age']['matrix']['F'][$band] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>총합</td>
                            <td class="num">{{ number_format($report['floating']['by_gender_age']['male']) }}</td>
                            <td class="num">{{ number_format($report['floating']['by_gender_age']['female']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</div>

{{-- ─── 카드매출 ─────────────────────────────────────────────── --}}
<div class="page-break">
    <h2>카드매출 분석</h2>

    <table class="cards">
        <tr>
            <td style="width:33%;">
                <div class="card-label">일평균 카드매출</div>
                <div class="card-value">{{ $money($report['sales']['total_amount']) }}원</div>
            </td>
            <td style="width:33%;">
                <div class="card-label">일평균 결제 건수</div>
                <div class="card-value">{{ number_format($report['sales']['total_count']) }}건</div>
            </td>
            <td style="width:34%;">
                <div class="card-label">건당 평균 결제</div>
                <div class="card-value">{{ number_format($report['sales']['avg_ticket']) }}원</div>
            </td>
        </tr>
    </table>

    <h3>업종별 매출</h3>
    <table class="data">
        <thead><tr><th>업종</th><th>대분류</th><th class="num">매출액</th><th class="num">결제 건수</th><th class="num">비중</th></tr></thead>
        <tbody>
            @forelse ($report['sales']['by_industry'] as $industry)
                <tr>
                    <td>{{ $industry['name'] }}</td>
                    <td style="color:#7b8394;">{{ $industry['group'] }}</td>
                    <td class="num">{{ $money($industry['amount']) }}원</td>
                    <td class="num">{{ number_format($industry['count']) }}</td>
                    <td class="num">{{ number_format($industry['share'], 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:#7b8394;">카드매출 데이터가 없습니다.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>요일 · 시간대별 매출</h3>
    @include('reports.partials.hbar', [
        'money' => true,
        'rows' => collect(Taxonomy::TIME_BANDS)->map(fn ($band) => [
            'label' => Taxonomy::TIME_BAND_LABELS[$band],
            'bars' => [
                ['value' => $report['sales']['by_day_band']['weekday'][$band] ?? 0, 'color' => '#0593ff'],
                ['value' => $report['sales']['by_day_band']['weekend'][$band] ?? 0, 'color' => '#09e092'],
            ],
        ])->all(),
    ])
    <div class="legend">
        <span class="dot" style="background-color:#0593ff;"></span>평일
        <span class="dot" style="background-color:#09e092; margin-left:3mm;"></span>주말
    </div>

    <h3>성 · 연령별 매출</h3>
    <div class="unit">단위 : 원</div>
    <table class="data">
        <thead><tr><th>연령</th><th class="num">남성</th><th class="num">여성</th><th class="num">합계</th></tr></thead>
        <tbody>
            @foreach (Taxonomy::AGE_BANDS as $band)
                @php
                    $male = $report['sales']['by_gender_age']['matrix']['M'][$band] ?? 0;
                    $female = $report['sales']['by_gender_age']['matrix']['F'][$band] ?? 0;
                @endphp
                <tr>
                    <td>{{ Taxonomy::AGE_LABELS[$band] }}</td>
                    <td class="num">{{ $money($male) }}</td>
                    <td class="num">{{ $money($female) }}</td>
                    <td class="num">{{ $money($male + $female) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>총합</td>
                <td class="num">{{ $money($report['sales']['by_gender_age']['male']) }}</td>
                <td class="num">{{ $money($report['sales']['by_gender_age']['female']) }}</td>
                <td class="num">{{ $money($report['sales']['by_gender_age']['total']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="insight">
        @foreach ($report['sales']['insights'] as $line)
            <p>{{ $line }}</p>
        @endforeach
    </div>
</div>

{{-- ─── 학생 · 학원 ──────────────────────────────────────────── --}}
<div class="page-break">
    <h2>학생 수 분석</h2>

    <h3>학생 수</h3>
    <table>
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 5mm;">
                @include('reports.partials.hbar', [
                    'money' => false,
                    'rows' => collect(Taxonomy::SCHOOL_TYPES)->map(fn ($type) => [
                        'label' => Taxonomy::SCHOOL_LABELS[$type],
                        'bars' => [['value' => $report['education']['students']['by_type'][$type] ?? 0, 'color' => '#00599d']],
                    ])->all(),
                ])
            </td>
            <td style="width: 48%; vertical-align: top;">
                <div class="unit">단위 : 명</div>
                <table class="data">
                    <thead><tr><th>구분</th><th class="num">학생 수</th></tr></thead>
                    <tbody>
                        @foreach (Taxonomy::SCHOOL_TYPES as $type)
                            @php $value = $report['education']['students']['by_type'][$type] ?? 0; @endphp
                            <tr>
                                <td>{{ Taxonomy::SCHOOL_LABELS[$type] }}</td>
                                <td class="num">{{ $value > 0 ? number_format($value) : '-' }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>총합</td>
                            <td class="num">{{ number_format($report['education']['students']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
    <p class="muted">* 해당 지역에 있는 학교별 학생 수로, 실제 거주 중인 학생 수와는 다른 정보입니다.</p>

    <h3>학원 수</h3>
    <table>
        <tr>
            <td style="width: 45%; vertical-align: top; padding-right: 5mm;">
                <div class="unit">단위 : 개</div>
                <table class="data">
                    <thead><tr><th>구분</th><th class="num">학원 수</th></tr></thead>
                    <tbody>
                        @foreach (Taxonomy::ACADEMY_CATEGORIES as $key => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="num">{{ number_format($report['education']['academies']['by_category'][$key] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>총합</td>
                            <td class="num">{{ number_format($report['education']['academies']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
            <td style="width: 55%; vertical-align: top;">
                <div class="unit">단위 : 개</div>
                <table class="data">
                    <thead><tr><th>업종명</th><th class="num">학원 수</th></tr></thead>
                    <tbody>
                        @forelse ($report['education']['academies']['by_industry'] as $academy)
                            <tr>
                                <td>{{ $academy['name'] }}</td>
                                <td class="num">{{ number_format($academy['count']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" style="color:#7b8394;">학원 데이터가 없습니다.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="insight">
        @foreach ($report['education']['insights'] as $line)
            <p>{{ $line }}</p>
        @endforeach
    </div>
</div>

{{-- ─── 데이터 출처 ──────────────────────────────────────────── --}}
<div>
    <h2>데이터 출처</h2>

    <table class="data" style="margin-top: 5mm;">
        <thead><tr><th>데이터</th><th>출처</th><th class="num">데이터 기준월</th></tr></thead>
        <tbody>
            @foreach ($report['sources'] as $source)
                <tr>
                    <td>{{ $source['label'] }}</td>
                    <td>{{ $source['provider'] }}</td>
                    <td class="num">{{ $source['base_label'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="muted" style="margin-top: 4mm;">
        * 보고서의 모든 정보는 집계 방법과 기준일, 분석 방법에 따라 오차가 발생할 수 있으니 단순 참고용으로만 활용해 주세요.<br>
        &nbsp;&nbsp;서면 제공 시에는 가맹사업법에서 정한 양식과 기준에 따라 작성해 주시기 바랍니다.
    </p>

    <div style="margin-top: 20mm; border-top: 0.5pt solid #d8e1ef; padding-top: 3mm; color:#7b8394; font-size:7.5pt;">
        MarketScope · 생성 {{ $meta['generated_at_full'] }} · 분석 ID {{ $analysis->uuid }}
    </div>
</div>

</body>
</html>
