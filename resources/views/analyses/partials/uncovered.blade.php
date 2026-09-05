{{--
    아직 수록되지 않은 통계 자리에 놓는 안내.
    0 을 사실처럼 그리는 대신 무엇이 왜 없는지 밝힌다.

    $reason  선택 (없으면 기본 문구)
--}}
<div class="mt-4 rounded-xl border border-dashed border-line-soft bg-ink-50/40 px-5 py-6 text-center">
    <p class="text-[13px] font-bold text-ink-500">이 지역은 아직 수록되지 않았습니다</p>
    <p class="mx-auto mt-1.5 max-w-[520px] text-[12px] leading-relaxed text-ink-400">
        {{ $reason ?? '해당 항목을 행정동 단위로 공개하는 출처가 아직 없어 값을 비워 두었습니다.' }}
    </p>
</div>
