@extends('layouts.app')

@section('title', '위치 상권 현황')
@section('heading', '위치 상권 현황')
@section('subheading', '지도에서 지점을 클릭하면 그 반경의 상권을 바로 계산합니다.')
@section('main-class', 'p-0')

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"
          integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"
            integrity="sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@endpush

@section('content')
<div class="flex h-[calc(100vh-4rem)] flex-col lg:flex-row"
     x-data="marketMap({
         center: @js($defaultCenter),
         radius: @js($defaultRadius),
         period: @js($defaultPeriod->code),
         urls: {
             market: @js(route('api.regions.market')),
             search: @js(route('api.regions.search')),
             store: @js(route('analyses.store')),
         },
     })">

    {{-- ─── 지도 ────────────────────────────────────────────── --}}
    <div class="relative h-[46vh] shrink-0 lg:h-auto lg:flex-1">
        <div id="market-map" class="absolute inset-0 z-0"></div>

        {{-- 검색 + 반경 --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 z-[500] p-4">
            <div class="pointer-events-auto mx-auto w-full max-w-xl">
                <div class="relative">
                    <input type="text" x-model="query" @input.debounce.300ms="search()" @focus="search()"
                           class="input pl-10 shadow-lg" placeholder="행정동으로 이동 (예: 가양1동, 마곡)" autocomplete="off">
                    <svg class="pointer-events-none absolute left-3.5 top-3 h-5 w-5 text-ink-300" fill="none"
                         stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/>
                    </svg>

                    <div x-show="results.length" x-cloak
                         class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-line bg-white shadow-xl">
                        <template x-for="r in results" :key="r.code">
                            <button type="button" @click="goTo(r)"
                                    class="block w-full px-4 py-2.5 text-left text-[14px] text-ink-700 hover:bg-surface-muted"
                                    x-text="r.full_name"></button>
                        </template>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @foreach ($radiusOptions as $option)
                        <button type="button" @click="setRadius({{ $option }})"
                                class="rounded-full border px-3.5 py-1.5 text-[12px] font-bold shadow-sm transition"
                                :class="radius === {{ $option }}
                                    ? 'border-brand-500 bg-brand-500 text-white'
                                    : 'border-line bg-white/95 text-ink-600 hover:border-brand-400'">
                            {{ $option >= 1000 ? rtrim(rtrim(number_format($option / 1000, 1), '0'), '.').'km' : $option.'m' }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- 안내 --}}
        <div x-show="!hasResult && !loading" x-cloak
             class="pointer-events-none absolute inset-x-0 bottom-6 z-[500] flex justify-center">
            <p class="rounded-full bg-ink-900/85 px-5 py-2.5 text-[13px] font-semibold text-white shadow-lg">
                지도를 클릭해 분석할 지점을 찍어 주세요
            </p>
        </div>
    </div>

    {{-- ─── 결과 패널 ────────────────────────────────────────── --}}
    <aside class="flex w-full flex-col overflow-y-auto border-t border-line-soft bg-white lg:w-[440px] lg:shrink-0 lg:border-l lg:border-t-0">

        {{-- 기간 --}}
        <div class="flex items-center justify-between gap-3 border-b border-line-soft px-5 py-3">
            <span class="text-[12px] font-bold text-ink-400">기준 기간</span>
            <select x-model="period" @change="refresh()" class="input !w-auto !py-1.5 text-[13px]">
                @foreach ($periods as $option)
                    <option value="{{ $option->code }}">{{ $option->label() }}</option>
                @endforeach
                @if (empty($periods))
                    <option value="{{ $defaultPeriod->code }}">{{ $defaultPeriod->label() }}</option>
                @endif
            </select>
        </div>

        {{-- 로딩 --}}
        <div x-show="loading" class="flex flex-1 items-center justify-center p-10">
            <div class="text-center">
                <svg class="mx-auto h-8 w-8 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                </svg>
                <p class="mt-3 text-[13px] text-ink-400">설정한 상권의 정보를 불러오고 있습니다…</p>
            </div>
        </div>

        {{-- 오류 --}}
        <div x-show="error" x-cloak class="m-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="text-[13px] leading-relaxed text-amber-800" x-text="error"></p>
        </div>

        {{-- 빈 상태 --}}
        <div x-show="!hasResult && !loading && !error" x-cloak class="flex flex-1 items-center justify-center p-10 text-center">
            <div>
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50">
                    <svg class="h-7 w-7 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/>
                        <circle cx="12" cy="10" r="2.5"/>
                    </svg>
                </div>
                <p class="mt-4 text-[15px] font-extrabold text-ink-900">지점을 선택해 주세요</p>
                <p class="mt-2 text-[13px] leading-relaxed text-ink-400">
                    지도를 클릭하거나 위에서 행정동을 검색하면<br>그 반경의 상권을 계산해 보여 드립니다.
                </p>
            </div>
        </div>

        {{-- 결과 --}}
        <div x-show="hasResult && !loading" x-cloak class="flex-1">

            {{-- 위치 --}}
            <div class="border-b border-line-soft px-5 py-4">
                <p class="text-[11px] font-bold tracking-wider text-brand-500 uppercase">Location</p>
                <p class="mt-1.5 text-[16px] font-extrabold text-ink-900" x-text="placeLabel"></p>
                <p class="mt-1 text-[12px] tabular-nums text-ink-400"
                   x-text="`반경 ${radius.toLocaleString()}m · ${center.lat.toFixed(5)}, ${center.lng.toFixed(5)}`"></p>

                <ul class="mt-3 space-y-2">
                    <template x-for="r in report?.meta?.regions ?? []" :key="r.code">
                        <li>
                            <div class="flex items-center justify-between gap-3 text-[12px]">
                                <span class="truncate font-semibold text-ink-700" x-text="r.name"></span>
                                <span class="shrink-0 tabular-nums text-ink-400" x-text="Math.round(r.weight * 100) + '%'"></span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-sunken">
                                <div class="h-full rounded-full bg-brand-500" :style="{ width: Math.round(r.weight * 100) + '%' }"></div>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- 핵심 지표 --}}
            <div class="border-b border-line-soft px-5 py-4">
                <p class="text-[13px] font-extrabold text-ink-900">핵심 지표</p>
                <div class="mt-3 grid grid-cols-2 gap-2.5">
                    <template x-for="m in summaryCards" :key="m.key">
                        <div class="rounded-xl border border-line-soft bg-surface-muted px-3.5 py-3">
                            <p class="text-[11px] font-semibold text-ink-400" x-text="m.label"></p>
                            <p class="mt-1 text-[19px] font-extrabold tabular-nums text-ink-900" x-text="m.value"></p>
                            <p class="mt-1 text-[10.5px] font-bold text-brand-600" x-text="m.level"></p>
                        </div>
                    </template>
                </div>
            </div>

            {{-- 시간대별 유동인구 --}}
            <div class="border-b border-line-soft px-5 py-4">
                <div class="flex items-baseline justify-between">
                    <p class="text-[13px] font-extrabold text-ink-900">시간대별 유동인구</p>
                    <span class="text-[11px] text-ink-300">일평균 · 명</span>
                </div>
                <div class="mt-3 space-y-2.5">
                    <template x-for="b in floatingBars" :key="b.band">
                        <div>
                            <div class="flex items-center justify-between text-[12px]">
                                <span class="font-semibold text-ink-700" x-text="b.label"></span>
                                <span class="tabular-nums text-ink-400" x-text="b.weekdayText"></span>
                            </div>
                            <div class="mt-1 space-y-1">
                                <div class="h-2 rounded-full bg-surface-sunken">
                                    <div class="h-full rounded-full bg-brand-500 transition-[width] duration-500" :style="{ width: b.weekdayPct + '%' }"></div>
                                </div>
                                {{-- 폭만 바꾸도록 객체 문법을 쓴다. 문자열 :style 은 배경색까지 덮어쓴다. --}}
                                <div class="h-2 rounded-full bg-surface-sunken">
                                    <div class="h-full rounded-full transition-[width] duration-500"
                                         style="background-color:#09e092"
                                         :style="{ width: b.weekendPct + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-3 flex gap-4 text-[11px] text-ink-400">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand-500"></span>평일</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background:#09e092"></span>주말</span>
                </div>
            </div>

            {{-- 카드매출 --}}
            <div class="border-b border-line-soft px-5 py-4">
                <div class="flex items-baseline justify-between">
                    <p class="text-[13px] font-extrabold text-ink-900">카드매출</p>
                    <span class="text-[11px] text-ink-300">일평균</span>
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2">
                    <template x-for="s in salesCards" :key="s.label">
                        <div class="rounded-lg bg-surface-muted px-3 py-2.5">
                            <p class="text-[10.5px] font-semibold text-ink-400" x-text="s.label"></p>
                            <p class="mt-0.5 text-[14px] font-extrabold tabular-nums text-ink-900" x-text="s.value"></p>
                        </div>
                    </template>
                </div>

                <ul class="mt-3.5 space-y-2">
                    <template x-for="i in topIndustries" :key="i.code">
                        <li>
                            <div class="flex items-center justify-between gap-3 text-[12px]">
                                <span class="truncate font-semibold text-ink-700" x-text="i.name"></span>
                                <span class="shrink-0 tabular-nums text-ink-400" x-text="i.share + '%'"></span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-sunken">
                                <div class="h-full rounded-full bg-brand-ink transition-[width] duration-500" :style="{ width: i.pct + '%' }"></div>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- 분석 결과 문단 --}}
            <div class="border-b border-line-soft px-5 py-4">
                <p class="text-[13px] font-extrabold text-ink-900">분석 결과</p>
                <ul class="mt-3 space-y-2">
                    <template x-for="(line, i) in (report?.summary?.insights ?? [])" :key="i">
                        <li class="flex gap-2 text-[12.5px] leading-relaxed text-ink-600">
                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span>
                            <span x-text="line"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        {{-- 리포트 만들기 --}}
        <div class="sticky bottom-0 border-t border-line-soft bg-white p-4">
            <form method="POST" action="{{ route('analyses.store') }}">
                @csrf
                <input type="hidden" name="mode" value="radius">
                <input type="hidden" name="center_lat" :value="center.lat">
                <input type="hidden" name="center_lng" :value="center.lng">
                <input type="hidden" name="radius_m" :value="radius">
                <input type="hidden" name="period" :value="period">
                <input type="hidden" name="address" :value="placeLabel">
                <input type="hidden" name="title" :value="reportTitle">

                <button type="submit" class="btn-primary w-full py-3 text-[14px]"
                        :disabled="!hasResult" :class="!hasResult && 'opacity-40'">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v10m0 0 4-4m-4 4-4-4M5 19h14"/>
                    </svg>
                    이 위치로 리포트 만들기
                </button>
            </form>
            <p class="mt-2 text-center text-[11px] text-ink-400">
                리포트를 만들면 저장되고 PDF로 내려받을 수 있습니다.
            </p>
        </div>
    </aside>
</div>

<script>
function marketMap(config) {
    return {
        center: { ...config.center },
        radius: config.radius,
        period: config.period,
        query: '',
        results: [],
        report: null,
        loading: false,
        error: '',
        placeLabel: '',
        map: null,
        marker: null,
        circle: null,

        get hasResult() {
            return this.report !== null;
        },

        get reportTitle() {
            const where = this.placeLabel || '선택 지점';
            return `${where} 반경 ${this.radius.toLocaleString()}m`;
        },

        // Alpine 이 컴포넌트를 붙일 때 init() 을 자동으로 부른다.
        // x-init 으로 또 부르면 지도가 두 번 만들어져 "already initialized" 가 난다.
        init() {
            this.$nextTick(() => this.initMap());
        },

        initMap() {
            this.map = L.map('market-map', { zoomControl: true, attributionControl: true })
                .setView([this.center.lat, this.center.lng], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);

            this.map.on('click', (e) => {
                this.center = { lat: e.latlng.lat, lng: e.latlng.lng };
                this.placeLabel = '';
                this.draw();
                this.refresh();
            });

            // 지도가 패널 옆에서 잘리지 않도록 크기를 다시 잡는다.
            setTimeout(() => this.map.invalidateSize(), 200);
            window.addEventListener('resize', () => this.map.invalidateSize());
        },

        draw() {
            const at = [this.center.lat, this.center.lng];

            if (!this.marker) {
                this.marker = L.circleMarker(at, {
                    radius: 7, color: '#ffffff', weight: 3, fillColor: '#0593ff', fillOpacity: 1,
                }).addTo(this.map);
            } else {
                this.marker.setLatLng(at);
            }

            if (!this.circle) {
                this.circle = L.circle(at, {
                    radius: this.radius, color: '#0593ff', weight: 2, dashArray: '6 8',
                    fillColor: '#0593ff', fillOpacity: 0.12,
                }).addTo(this.map);
            } else {
                this.circle.setLatLng(at).setRadius(this.radius);
            }

            this.map.fitBounds(this.circle.getBounds(), { padding: [40, 40], maxZoom: 16 });
        },

        setRadius(value) {
            this.radius = value;
            if (!this.marker) return;
            this.draw();
            this.refresh();
        },

        async search() {
            const keyword = this.query.trim();

            if (!keyword) {
                this.results = [];
                return;
            }

            const res = await fetch(`${config.urls.search}?q=${encodeURIComponent(keyword)}`, {
                headers: { Accept: 'application/json' },
            });
            this.results = (await res.json()).data ?? [];
        },

        goTo(region) {
            this.results = [];
            this.query = '';
            this.center = { lat: Number(region.lat), lng: Number(region.lng) };
            this.placeLabel = region.full_name;
            this.draw();
            this.refresh();
        },

        async refresh() {
            if (!this.marker) return;

            this.loading = true;
            this.error = '';

            try {
                const params = new URLSearchParams({
                    lat: this.center.lat, lng: this.center.lng,
                    radius_m: this.radius, period: this.period,
                });
                const res = await fetch(`${config.urls.market}?${params}`, {
                    headers: { Accept: 'application/json' },
                });
                const json = await res.json();

                if (!res.ok || !json.ok) {
                    this.report = null;
                    this.error = json.message ?? '이 지점의 상권 데이터를 찾지 못했습니다.';
                    return;
                }

                this.report = json.data;

                if (!this.placeLabel) {
                    this.placeLabel = this.report.meta.regions[0]?.name ?? '';
                }
            } catch (e) {
                this.report = null;
                this.error = '분석 중 문제가 발생했습니다. 잠시 후 다시 시도해 주세요.';
            } finally {
                this.loading = false;
            }
        },

        /* ── 패널 표시용 가공 ─────────────────────────────── */

        get summaryCards() {
            const s = this.report?.summary;
            if (!s) return [];

            return [
                { key: 'resident', label: '거주 인구' },
                { key: 'households', label: '배후세대' },
                { key: 'lunch_floating', label: '점심 유동(일평균)' },
                { key: 'workplace', label: '직장인구' },
            ].map((m) => ({
                ...m,
                value: (s.selected[m.key] ?? 0).toLocaleString(),
                level: `${s.sido_name} 평균 대비 ${s.levels[m.key] ?? '-'}`,
            }));
        },

        get floatingBars() {
            const byDay = this.report?.floating?.by_day_band;
            if (!byDay) return [];

            const labels = { morning: '오전', lunch: '점심', afternoon: '오후', evening: '저녁', night: '밤' };
            const max = Math.max(1, ...Object.values(byDay.weekday), ...Object.values(byDay.weekend));

            return Object.keys(labels).map((band) => ({
                band,
                label: labels[band],
                weekdayText: (byDay.weekday[band] ?? 0).toLocaleString(),
                weekdayPct: Math.round(((byDay.weekday[band] ?? 0) / max) * 100),
                weekendPct: Math.round(((byDay.weekend[band] ?? 0) / max) * 100),
            }));
        },

        get salesCards() {
            const s = this.report?.sales;
            if (!s) return [];

            return [
                { label: '매출액', value: this.money(s.total_amount) },
                { label: '결제 건수', value: (s.total_count ?? 0).toLocaleString() },
                { label: '건당', value: (s.avg_ticket ?? 0).toLocaleString() + '원' },
            ];
        },

        get topIndustries() {
            const list = this.report?.sales?.by_industry ?? [];
            const max = Math.max(1, ...list.map((i) => i.share));

            return list.slice(0, 6).map((i) => ({ ...i, pct: Math.round((i.share / max) * 100) }));
        },

        money(amount) {
            amount = amount ?? 0;
            if (amount >= 100000000) return (amount / 100000000).toFixed(1) + '억원';
            if (amount >= 10000) return Math.round(amount / 10000).toLocaleString() + '만원';
            return amount.toLocaleString() + '원';
        },
    };
}
</script>
@endsection
