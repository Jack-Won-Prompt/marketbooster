@if (! empty($lines))
    <div class="mt-6 rounded-xl border border-line-soft bg-surface-muted p-5">
        <p class="text-[13px] font-extrabold text-ink-900">분석 결과</p>
        <ul class="mt-3 space-y-2">
            @foreach ($lines as $line)
                <li class="flex gap-2.5 text-[14px] leading-relaxed text-ink-700">
                    <span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span>
                    <span>{{ $line }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
