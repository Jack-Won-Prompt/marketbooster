{{--
    선택 범위 중 일부만 수록된 항목 안내.

    서울과 경기가 함께 걸리는 반경에서 유동인구·카드매출이 서울 쪽에서만 잡히면
    합계가 범위 전체를 대표하지 못한다. 숫자를 그대로 두되 무엇이 빠졌는지 밝힌다.

    $ratio  0.0 ~ 1.0
--}}
@if (($ratio ?? 1) < 0.99)
    <p class="mt-2 text-[12px] leading-relaxed text-amber-700">
        이 범위의 <strong>{{ number_format($ratio * 100, 0) }}%</strong>만 수록돼 있습니다.
        나머지 지역은 공개 출처가 없어 합계에서 빠졌습니다.
    </p>
@endif
