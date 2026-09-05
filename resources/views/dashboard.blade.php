@extends('layouts.app')

@section('title', '대시보드')
@section('heading', '대시보드')
@section('subheading', auth()->user()->name . '님, 오늘도 상권을 살펴볼까요?')

@section('actions')
    <a href="{{ route('analyses.create') }}" class="btn-primary btn-sm">새 상권분석</a>
@endsection

@section('content')
<div class="space-y-6">

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['내 분석', number_format($totalAnalyses), '건'],
            ['완료된 리포트', number_format($completedAnalyses), '건'],
            ['수록 행정동', number_format($regionCount), '곳'],
            ['관심지역', number_format($favorites->count()), '곳'],
        ] as [$label, $value, $unit])
            <div class="card-pad">
                <p class="text-[13px] font-semibold text-ink-400">{{ $label }}</p>
                <p class="mt-2 text-[28px] font-extrabold leading-none tabular-nums text-ink-900">
                    {{ $value }}<span class="ml-1 text-[14px] font-bold text-ink-400">{{ $unit }}</span>
                </p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <section class="card-pad">
            <div class="flex items-center justify-between">
                <h2 class="text-[16px] font-extrabold text-ink-900">최근 분석</h2>
                <a href="{{ route('analyses.index') }}" class="text-[13px] font-bold text-brand-600 hover:underline">전체 보기</a>
            </div>

            @if ($analyses->isEmpty())
                <div class="mt-5 rounded-xl border border-dashed border-line px-4 py-10 text-center">
                    <p class="text-[14px] font-bold text-ink-700">아직 만든 리포트가 없습니다</p>
                    <p class="mt-1.5 text-[13px] text-ink-400">지역을 하나 골라 첫 상권분석을 시작해 보세요.</p>
                    <a href="{{ route('analyses.create') }}" class="btn-primary btn-sm mt-5">상권분석 시작</a>
                </div>
            @else
                <ul class="mt-4 divide-y divide-line-soft">
                    @foreach ($analyses as $analysis)
                        <li class="flex items-center justify-between gap-4 py-3.5">
                            <div class="min-w-0">
                                <a href="{{ route('analyses.show', $analysis) }}"
                                   class="block truncate text-[14px] font-bold text-ink-900 hover:text-brand-600">
                                    {{ $analysis->title }}
                                </a>
                                <p class="mt-0.5 truncate text-[12px] text-ink-400">
                                    {{ $analysis->rangeLabel() }} · {{ $analysis->created_at->format('Y.m.d H:i') }}
                                </p>
                            </div>
                            @php
                                $badge = match ($analysis->status) {
                                    'completed' => ['완료', 'bg-brand-50 text-brand-600'],
                                    'failed' => ['실패', 'bg-red-50 text-red-600'],
                                    default => ['진행 중', 'bg-surface-sunken text-ink-500'],
                                };
                            @endphp
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge[1] }}">{{ $badge[0] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="space-y-6">
            <section class="card-pad">
                <h2 class="text-[16px] font-extrabold text-ink-900">관심지역</h2>
                @if ($favorites->isEmpty())
                    <p class="mt-4 text-[13px] leading-relaxed text-ink-400">
                        자주 보는 행정동을 관심지역으로 등록하면 분석 화면에서 한 번에 불러올 수 있습니다.
                    </p>
                @else
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($favorites as $favorite)
                            <span class="chip">{{ $favorite->region?->full_name ?? $favorite->label }}</span>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card-pad">
                <h2 class="text-[16px] font-extrabold text-ink-900">데이터 기준월</h2>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($sources as $source)
                        <li class="flex items-center justify-between text-[13px]">
                            <span class="text-ink-500">{{ $source->label }}</span>
                            <span class="font-bold text-ink-700">{{ $source->base_label ?? '-' }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
</div>
@endsection
