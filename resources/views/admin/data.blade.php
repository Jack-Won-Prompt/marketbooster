@extends('layouts.app')

@section('title', '데이터 현황')
@section('heading', '데이터 현황')
@section('subheading', '어떤 통계가 어느 기간까지 쌓여 있는지 확인합니다.')

@section('content')
<div class="space-y-6">

    @unless ($hasServiceKey)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
            <p class="text-[14px] font-extrabold text-amber-900">공공데이터포털 인증키가 설정되지 않았습니다</p>
            <p class="mt-1.5 text-[13px] leading-relaxed text-amber-800">
                data.go.kr 에서 활용신청 후 발급받은 Decoding 키를 <code class="rounded bg-white px-1.5 py-0.5">.env</code> 의
                <code class="rounded bg-white px-1.5 py-0.5">OPENDATA_SERVICE_KEY</code> 에 넣고
                <code class="rounded bg-white px-1.5 py-0.5">php artisan config:clear</code> 를 실행하세요.
                키가 없어도 CSV 파일데이터는 바로 적재할 수 있습니다.
            </p>
        </div>
    @endunless

    <section class="card-pad">
        <h2 class="text-[16px] font-extrabold text-ink-900">시도별 수록 범위</h2>
        <p class="mt-1.5 text-[13px] leading-relaxed text-ink-500">
            행정동 경계는 전국을 넣을 수 있지만 통계 출처는 시도마다 다릅니다.
            어디까지 분석이 되는지는 이 표가 기준입니다.
        </p>
        <div class="mt-4 overflow-x-auto">
            <table class="table-report">
                <thead>
                    <tr>
                        <th>시도</th>
                        <th class="!text-right">행정동</th>
                        <th class="!text-right">경계</th>
                        @foreach (array_keys($sidoCoverage[0]['datasets'] ?? []) as $label)
                            <th class="!text-center">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sidoCoverage as $row)
                        <tr>
                            <td class="font-semibold text-ink-900">{{ $row['sido'] }}</td>
                            <td class="num">{{ number_format($row['dongs']) }}</td>
                            <td class="num">{{ number_format($row['boundaries']) }}</td>
                            @foreach ($row['datasets'] as $has)
                                <td class="text-center">
                                    <span class="{{ $has ? 'text-brand-600' : 'text-ink-300' }} font-bold">
                                        {{ $has ? '●' : '–' }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-[12px] leading-relaxed text-ink-400">
            * 새 시도를 넣으려면 <code class="rounded bg-surface-muted px-1.5 py-0.5">php artisan regions:import 경기도 --download</code> 로
            행정동 경계를 먼저 적재한 뒤, 시도별 통계를 수집하세요.
        </p>
    </section>

    <section class="card-pad">
        <h2 class="text-[16px] font-extrabold text-ink-900">적재 현황</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="table-report">
                <thead>
                    <tr>
                        <th>데이터</th>
                        <th>테이블</th>
                        <th class="!text-right">행 수</th>
                        <th class="!text-right">수록 행정동</th>
                        <th class="!text-right">기간 수</th>
                        <th class="!text-right">최신 기준</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($coverage as $row)
                        <tr>
                            <td class="font-semibold text-ink-900">{{ $row['label'] }}</td>
                            <td class="font-mono text-[12px] text-ink-400">{{ $row['table'] }}</td>
                            <td class="num">{{ number_format($row['rows']) }}</td>
                            <td class="num">{{ number_format($row['regions']) }}</td>
                            <td class="num">{{ number_format($row['periods']) }}</td>
                            <td class="num font-bold text-ink-900">{{ $row['latest'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card-pad">
            <h2 class="text-[16px] font-extrabold text-ink-900">수집 방법</h2>
            <p class="mt-2 text-[13px] leading-relaxed text-ink-500">
                아래 명령을 프로젝트 루트에서 실행하면 통계가 같은 스키마로 적재됩니다.
                같은 지역·같은 기간의 기존 행은 덮어써집니다.
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <p class="text-[12px] font-bold text-ink-700">행정동 경계 (모든 분석의 기준)</p>
                    <pre class="mt-1.5 overflow-x-auto rounded-lg bg-ink-900 px-4 py-3 text-[12px] leading-relaxed text-white"><code>php artisan regions:import 경기도 --download</code></pre>
                    <p class="mt-2 text-[12px] leading-relaxed text-ink-400">
                        시도명을 여러 개 적을 수 있고, 생략하면 전국을 적재합니다.
                        인증키가 필요 없습니다.
                    </p>
                </div>

                <div>
                    <p class="text-[12px] font-bold text-ink-700">
                        서울시 상권분석서비스 (분기 단위)
                        <span class="ml-1.5 rounded-full px-2 py-0.5 text-[11px] font-bold
                            {{ $hasSeoulKey ? 'bg-brand-50 text-brand-600' : 'bg-amber-50 text-amber-700' }}">
                            {{ $hasSeoulKey ? '인증키 설정됨' : '인증키 없음' }}
                        </span>
                    </p>
                    <pre class="mt-1.5 overflow-x-auto rounded-lg bg-ink-900 px-4 py-3 text-[12px] leading-relaxed text-white"><code>php artisan seoul:sync all --yq=20242</code></pre>
                    <p class="mt-2 text-[12px] leading-relaxed text-ink-400">
                        인증키 1개로 아래 서비스를 모두 씁니다 (API 별 활용신청 없음).<br>
                        @foreach ($seoulDatasets as $key => $definition)
                            <span class="font-mono">{{ $definition['service'] }}</span> {{ $definition['label'] }}@if (! $loop->last) · @endif
                        @endforeach
                    </p>
                </div>

                <div>
                    <p class="text-[12px] font-bold text-ink-700">
                        상가(상권)정보
                        <span class="ml-1.5 text-[11px] font-normal text-ink-400">점포 {{ number_format($storeCount) }}건 적재됨</span>
                    </p>
                    <pre class="mt-1.5 overflow-x-auto rounded-lg bg-ink-900 px-4 py-3 text-[12px] leading-relaxed text-white"><code>php artisan sbiz:sync-stores --sido=경기도 --skip-collected</code></pre>
                    <p class="mt-2 text-[12px] leading-relaxed text-ink-400">
                        data.go.kr 활용신청이 필요합니다 (자동승인).
                    </p>
                </div>

                <div>
                    <p class="text-[12px] font-bold text-ink-700">CSV 파일데이터 적재</p>
                    <pre class="mt-1.5 overflow-x-auto rounded-lg bg-ink-900 px-4 py-3 text-[12px] leading-relaxed text-white"><code>php artisan opendata:import card_sales &lt;파일.csv&gt; --ym=202608
php artisan opendata:import card_sales &lt;파일.csv&gt; --ym=20242</code></pre>
                    <p class="mt-2 text-[12px] leading-relaxed text-ink-400">
                        6자리는 월(YYYYMM), 5자리는 분기(YYYYQ)로 해석합니다.<br>
                        사용 가능한 종류: {{ implode(', ', $importTypes) }}
                    </p>
                </div>
            </div>
        </section>

        <section class="card-pad">
            <h2 class="text-[16px] font-extrabold text-ink-900">데이터 출처</h2>
            <table class="table-report mt-4">
                <thead><tr><th>데이터</th><th>출처</th><th class="!text-right">기준 기간</th></tr></thead>
                <tbody>
                    @foreach ($sources as $source)
                        <tr>
                            <td class="font-semibold text-ink-900">{{ $source->label }}</td>
                            <td class="text-[12px]">{{ $source->provider }}</td>
                            <td class="num">{{ $source->base_label ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>

    <section class="card-pad">
        <h2 class="text-[16px] font-extrabold text-ink-900">최근 수집 이력</h2>
        @if ($logs->isEmpty())
            <p class="mt-4 text-[13px] text-ink-400">아직 수집 이력이 없습니다.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="table-report">
                    <thead>
                        <tr>
                            <th>종류</th><th>경로</th><th>기준 기간</th>
                            <th class="!text-right">저장</th><th class="!text-right">제외</th>
                            <th>상태</th><th class="!text-right">시각</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="font-semibold text-ink-900">{{ $log->type }}</td>
                                <td class="max-w-[220px] truncate text-[12px] text-ink-400">{{ $log->reference }}</td>
                                <td>{{ $log->base_yq ?: ($log->base_ym ?: '-') }}</td>
                                <td class="num">{{ number_format($log->rows_imported) }}</td>
                                <td class="num">{{ number_format($log->rows_skipped) }}</td>
                                <td>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold
                                        {{ $log->status === 'success' ? 'bg-brand-50 text-brand-600' : ($log->status === 'failed' ? 'bg-red-50 text-red-600' : 'bg-surface-sunken text-ink-500') }}">
                                        {{ $log->status }}
                                    </span>
                                </td>
                                <td class="num text-[12px] text-ink-400">{{ $log->created_at->format('m.d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
