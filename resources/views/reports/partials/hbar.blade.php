@php
    /**
     * dompdf 에서 안전하게 그려지는 가로 막대 차트.
     * flex/canvas 대신 표와 배경색 div 만 사용한다.
     * 막대 영역과 값 라벨을 각각 별도 칸에 두어 긴 막대에서도 줄바꿈이 생기지 않는다.
     *
     * @var array $rows  [['label' => '20대', 'bars' => [['value' => 100, 'color' => '#0593ff']]], ...]
     * @var bool  $money 금액이면 억/만 단위로 축약
     */
    $max = 0;
    foreach ($rows as $row) {
        foreach ($row['bars'] as $bar) {
            $max = max($max, $bar['value']);
        }
    }
    $max = $max ?: 1;

    $format = function ($value) use ($money) {
        if (! ($money ?? false)) {
            return number_format($value);
        }

        return $value >= 100000000
            ? number_format($value / 100000000, 1).'억'
            : ($value >= 10000 ? number_format($value / 10000).'만' : number_format($value));
    };
@endphp

<table class="chart">
    @foreach ($rows as $row)
        <tr>
            <td class="chart-label">{{ $row['label'] }}</td>
            <td class="chart-track">
                <table class="chart-inner">
                    @foreach ($row['bars'] as $bar)
                        <tr>
                            <td class="chart-bar-cell">
                                <div class="chart-bar"
                                     style="width: {{ max(0.8, round($bar['value'] / $max * 100, 1)) }}%; background-color: {{ $bar['color'] }};"></div>
                            </td>
                            <td class="chart-value">{{ $format($bar['value']) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    @endforeach
</table>
