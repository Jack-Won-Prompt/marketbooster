@php
    /**
     * 성 × 연령 교차표 + 막대차트.
     *
     * @var array  $data      ['matrix' => ['M'=>[age=>n],'F'=>[...]], 'male','female','total']
     * @var array  $ageBands  표시할 연령 구간 순서
     * @var string $chartId   canvas 식별자
     * @var string $unit      단위 표기 (명 / 원)
     * @var bool   $asMoney   금액 여부 (축 라벨을 억/만 단위로 축약)
     */
    $ageBands = $ageBands ?? \App\Support\Taxonomy::AGE_BANDS;
    $asMoney = $asMoney ?? false;
    $labels = array_map(fn ($band) => \App\Support\Taxonomy::AGE_LABELS[$band] ?? $band, $ageBands);
    $chartConfig = [
        'labels' => $labels,
        'datasets' => [
            ['label' => '남성', 'data' => array_map(fn ($b) => $data['matrix']['M'][$b] ?? 0, $ageBands), 'color' => '#0593ff'],
            ['label' => '여성', 'data' => array_map(fn ($b) => $data['matrix']['F'][$b] ?? 0, $ageBands), 'color' => '#f2557b'],
        ],
    ];
@endphp

<div class="grid gap-6 lg:grid-cols-[1.35fr_1fr]">
    <div class="rounded-xl border border-line-soft p-4">
        <div class="h-[260px]">
            <canvas data-chart="bar" data-chart-money="{{ $asMoney ? '1' : '0' }}"
                    data-chart-config='@json($chartConfig)' id="{{ $chartId }}"></canvas>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="table-report">
            <thead>
                <tr>
                    <th rowspan="2" class="align-bottom">연령</th>
                    <th colspan="2" class="!text-center">성별</th>
                </tr>
                <tr>
                    <th class="!text-right">남성</th>
                    <th class="!text-right">여성</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ageBands as $band)
                    <tr>
                        <td class="font-semibold text-ink-900">{{ \App\Support\Taxonomy::AGE_LABELS[$band] ?? $band }}</td>
                        <td class="num">{{ number_format($data['matrix']['M'][$band] ?? 0) }}</td>
                        <td class="num">{{ number_format($data['matrix']['F'][$band] ?? 0) }}</td>
                    </tr>
                @endforeach
                <tr class="bg-surface-muted font-bold">
                    <td class="font-extrabold text-ink-900">총합</td>
                    <td class="num font-extrabold text-ink-900">{{ number_format($data['male']) }}</td>
                    <td class="num font-extrabold text-ink-900">{{ number_format($data['female']) }}</td>
                </tr>
            </tbody>
        </table>
        <p class="mt-2 text-right text-[12px] text-ink-300">단위 : {{ $unit ?? '명' }}</p>
    </div>
</div>
