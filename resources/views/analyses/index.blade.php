@extends('layouts.app')

@section('title', '분석 목록')
@section('heading', '분석 목록')
@section('subheading', '지금까지 만든 상권분석 리포트입니다.')

@section('actions')
    <a href="{{ route('analyses.create') }}" class="btn-primary btn-sm">새 상권분석</a>
@endsection

@section('content')
@if ($analyses->isEmpty())
    <div class="card-pad text-center">
        <p class="text-[16px] font-extrabold text-ink-900">아직 만든 리포트가 없습니다</p>
        <p class="mt-2 text-[14px] text-ink-500">지역을 하나 골라 첫 상권분석을 시작해 보세요.</p>
        <a href="{{ route('analyses.create') }}" class="btn-primary mt-6">상권분석 시작</a>
    </div>
@else
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($analyses as $analysis)
            @php
                $payload = $analysis->payload ?? [];
                $selected = $payload['summary']['selected'] ?? null;
                $badge = match ($analysis->status) {
                    'completed' => ['완료', 'bg-brand-50 text-brand-600'],
                    'failed' => ['실패', 'bg-red-50 text-red-600'],
                    default => ['진행 중', 'bg-surface-sunken text-ink-500'],
                };
            @endphp

            <article class="card flex flex-col p-5 transition hover:border-brand-400">
                <div class="flex items-start justify-between gap-3">
                    <a href="{{ route('analyses.show', $analysis) }}" class="text-[15px] font-extrabold leading-snug text-ink-900 hover:text-brand-600">
                        {{ $analysis->title }}
                    </a>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge[1] }}">{{ $badge[0] }}</span>
                </div>

                <p class="mt-1.5 text-[12px] text-ink-400">
                    {{ $analysis->rangeLabel() }} ·
                    {{ \Illuminate\Support\Carbon::createFromFormat('Ym', $analysis->base_ym)->format('Y년 n월') }} 기준
                </p>

                @if ($selected)
                    <dl class="mt-4 grid grid-cols-2 gap-2.5">
                        @foreach ([['거주인구', $selected['resident']], ['배후세대', $selected['households']], ['점심 유동', $selected['lunch_floating']], ['직장인구', $selected['workplace']]] as [$label, $value])
                            <div class="rounded-lg bg-surface-muted px-3 py-2">
                                <dt class="text-[11px] font-semibold text-ink-400">{{ $label }}</dt>
                                <dd class="mt-0.5 text-[15px] font-extrabold tabular-nums text-ink-900">{{ number_format($value) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-4 rounded-lg bg-surface-muted px-3 py-4 text-center text-[12px] text-ink-400">
                        {{ $analysis->error_message ?? '결과를 기다리는 중입니다.' }}
                    </p>
                @endif

                <div class="mt-5 flex items-center justify-between gap-2 border-t border-line-soft pt-4">
                    <span class="text-[12px] text-ink-300">{{ $analysis->created_at->format('Y.m.d H:i') }}</span>
                    <div class="flex items-center gap-2">
                        @if ($analysis->isCompleted())
                            <a href="{{ route('analyses.pdf', $analysis) }}" class="text-[12px] font-bold text-ink-500 hover:text-brand-600">PDF</a>
                        @endif
                        <a href="{{ route('analyses.show', $analysis) }}" class="text-[12px] font-bold text-brand-600 hover:underline">리포트 보기</a>
                        <form method="POST" action="{{ route('analyses.destroy', $analysis) }}"
                              onsubmit="return confirm('이 분석을 삭제할까요?')">
                            @csrf @method('DELETE')
                            <button class="text-[12px] font-bold text-ink-300 hover:text-red-500">삭제</button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-8">{{ $analyses->links() }}</div>
@endif
@endsection
