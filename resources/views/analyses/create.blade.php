@extends('layouts.app')

@section('title', '새 상권분석')
@section('heading', '새 상권분석')
@section('subheading', '분석할 지역을 고르면 리포트를 만들어 드립니다.')

@push('head')
    @if (config('map.kakao_js_key'))
        <script src="//dapi.kakao.com/v2/maps/sdk.js?appkey={{ config('map.kakao_js_key') }}&libraries=services"></script>
    @endif
@endpush

@section('content')
<form method="POST" action="{{ route('analyses.store') }}"
      x-data="analysisForm({
          mode: @js(old('mode', 'radius')),
          radius: @js((int) old('radius_m', $defaultRadius)),
          period: @js(old('period', $defaultPeriod->code)),
          center: @js(['lat' => (float) old('center_lat', $defaultCenter['lat']), 'lng' => (float) old('center_lng', $defaultCenter['lng'])]),
          hasMapKey: @js((bool) config('map.kakao_js_key')),
          previewUrl: @js(route('api.regions.preview')),
          searchUrl: @js(route('api.regions.search')),
      })"
      x-init="init()"
      class="grid gap-6 xl:grid-cols-[1.35fr_1fr]">
    @csrf

    <input type="hidden" name="mode" :value="mode">
    <input type="hidden" name="center_lat" :value="mode === 'radius' ? center.lat : ''">
    <input type="hidden" name="center_lng" :value="mode === 'radius' ? center.lng : ''">
    <input type="hidden" name="radius_m" :value="mode === 'radius' ? radius : ''">
    <input type="hidden" name="address" :value="address">
    <input type="hidden" name="period" :value="period">
    <template x-for="code in selectedCodes" :key="code">
        <input type="hidden" name="region_codes[]" :value="code">
    </template>

    {{-- ─── 왼쪽: 지역 선택 ─────────────────────────────────── --}}
    <div class="space-y-5">
        {{-- 분석 방식 --}}
        <div class="card-pad">
            <p class="text-[15px] font-extrabold text-ink-900">1. 분석 방식</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button type="button" @click="setMode('radius')"
                        class="rounded-xl border p-4 text-left transition"
                        :class="mode === 'radius' ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500/20' : 'border-line hover:border-brand-400'">
                    <p class="text-[14px] font-extrabold text-ink-900">반경으로 분석</p>
                    <p class="mt-1 text-[13px] leading-relaxed text-ink-500">
                        중심 지점에서 원을 그려 걸치는 행정동을 면적 비율로 안분합니다.
                    </p>
                </button>
                <button type="button" @click="setMode('region')"
                        class="rounded-xl border p-4 text-left transition"
                        :class="mode === 'region' ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500/20' : 'border-line hover:border-brand-400'">
                    <p class="text-[14px] font-extrabold text-ink-900">행정동으로 분석</p>
                    <p class="mt-1 text-[13px] leading-relaxed text-ink-500">
                        행정동을 직접 골라 통계를 그대로 합산합니다. 최대 30곳.
                    </p>
                </button>
            </div>
        </div>

        {{-- 지역 검색 --}}
        <div class="card-pad">
            <div class="flex items-center justify-between gap-4">
                <p class="text-[15px] font-extrabold text-ink-900">2. 지역 선택</p>
                <span class="chip" x-text="mode === 'radius' ? '중심 지점을 정하세요' : '행정동을 고르세요'"></span>
            </div>

            <div class="relative mt-4">
                <input type="text" x-model="query" @input.debounce.300ms="search()" @focus="search()"
                       class="input pl-10" placeholder="행정동 검색 (예: 가양1동, 강서구, 마곡)" autocomplete="off">
                <svg class="pointer-events-none absolute left-3.5 top-3 h-5 w-5 text-ink-300" fill="none"
                     stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/>
                </svg>

                <div x-show="results.length" x-cloak
                     class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-line bg-white shadow-lg">
                    <template x-for="region in results" :key="region.code">
                        <button type="button" @click="pick(region)"
                                class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-[14px] hover:bg-surface-muted">
                            <span class="text-ink-700" x-text="region.full_name"></span>
                            <span class="text-[12px] text-ink-300" x-text="region.code"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- 지도 또는 대체 패널 --}}
            <div class="mt-4">
                <template x-if="hasMapKey">
                    <div id="map" class="h-[340px] w-full rounded-xl border border-line"></div>
                </template>

                <template x-if="!hasMapKey">
                    <div class="rounded-xl border border-dashed border-line bg-surface-muted p-6 text-center">
                        <p class="text-[14px] font-bold text-ink-700">지도 키가 설정되지 않았습니다</p>
                        <p class="mt-1.5 text-[13px] leading-relaxed text-ink-500">
                            .env 의 <code class="rounded bg-white px-1.5 py-0.5 text-[12px]">KAKAO_MAP_JS_KEY</code> 를 채우면
                            지도에서 중심을 찍을 수 있습니다. 지금은 검색으로 중심 지점을 정하거나 좌표를 직접 입력하세요.
                        </p>
                        <div class="mx-auto mt-4 grid max-w-sm grid-cols-2 gap-3">
                            <div>
                                <label class="label">위도</label>
                                <input type="number" step="0.000001" x-model.number="center.lat"
                                       @change="refreshPreview()" class="input">
                            </div>
                            <div>
                                <label class="label">경도</label>
                                <input type="number" step="0.000001" x-model.number="center.lng"
                                       @change="refreshPreview()" class="input">
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- 반경 슬라이더 --}}
            <div x-show="mode === 'radius'" x-cloak class="mt-5">
                <div class="flex items-center justify-between">
                    <label class="label !mb-0">분석 반경</label>
                    <span class="text-[14px] font-extrabold tabular-nums text-brand-600"
                          x-text="radius.toLocaleString() + 'm'"></span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($radiusOptions as $option)
                        <button type="button" @click="setRadius({{ $option }})"
                                class="rounded-full border px-4 py-1.5 text-[13px] font-semibold transition"
                                :class="radius === {{ $option }} ? 'border-brand-500 bg-brand-50 text-brand-600' : 'border-line text-ink-500 hover:border-brand-400'">
                            {{ number_format($option) }}m
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- 선택된 행정동 --}}
            <div x-show="mode === 'region'" x-cloak class="mt-5">
                <label class="label">선택한 행정동 <span x-text="'(' + selected.length + ')'"></span></label>
                <div x-show="!selected.length" class="rounded-xl border border-dashed border-line px-4 py-6 text-center text-[13px] text-ink-400">
                    위 검색창에서 행정동을 골라 추가하세요.
                </div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="region in selected" :key="region.code">
                        <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3.5 py-1.5 text-[13px] font-semibold text-brand-700">
                            <span x-text="region.full_name"></span>
                            <button type="button" @click="remove(region.code)" class="text-brand-400 hover:text-brand-700">&times;</button>
                        </span>
                    </template>
                </div>
            </div>

            @if ($favorites->isNotEmpty())
                <div class="mt-5 border-t border-line-soft pt-4">
                    <p class="label">관심지역에서 바로 선택</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($favorites as $favorite)
                            @continue(! $favorite->region)
                            <button type="button"
                                    @click="pick(@js([
                                        'code' => $favorite->region->code,
                                        'full_name' => $favorite->region->full_name,
                                        'lat' => (float) $favorite->region->lat,
                                        'lng' => (float) $favorite->region->lng,
                                    ]))"
                                    class="chip hover:border-brand-400 hover:text-brand-600">
                                {{ $favorite->region->full_name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ─── 오른쪽: 요약 & 실행 ─────────────────────────────── --}}
    <div class="space-y-5">
        <div class="card-pad">
            <p class="text-[15px] font-extrabold text-ink-900">3. 리포트 정보</p>

            <div class="mt-4">
                <label class="label" for="title">분석 이름</label>
                <input id="title" name="title" x-model="title" required maxlength="120" class="input"
                       placeholder="예) 마곡나루역 반경 1km">
            </div>

            <div class="mt-4">
                <label class="label" for="period">기준 기간</label>
                <select id="period" x-model="period" class="input">
                    @foreach ($periods as $option)
                        <option value="{{ $option->code }}">
                            {{ $option->label() }}{{ $option->isQuarter() ? ' (분기)' : ' (월)' }}
                        </option>
                    @endforeach
                    @if (empty($periods))
                        <option value="{{ $defaultPeriod->code }}">{{ $defaultPeriod->label() }}</option>
                    @endif
                </select>
                <p class="mt-1.5 text-[12px] leading-relaxed text-ink-400">
                    서울시 상권분석서비스는 분기 단위, 그 밖의 출처는 월 단위로 제공됩니다.
                </p>
            </div>

            <div class="mt-4 rounded-xl border border-line-soft bg-surface-muted px-4 py-3">
                <div class="flex items-center justify-between text-[13px]">
                    <span class="text-ink-400">분석 범위</span>
                    <span class="font-bold text-ink-700"
                          x-text="mode === 'radius' ? '반경 ' + radius.toLocaleString() + 'm' : '행정동 ' + selected.length + '곳'"></span>
                </div>
                <div class="mt-2 flex items-center justify-between text-[13px]" x-show="mode === 'radius'">
                    <span class="text-ink-400">중심 좌표</span>
                    <span class="font-bold tabular-nums text-ink-700"
                          x-text="center.lat.toFixed(5) + ', ' + center.lng.toFixed(5)"></span>
                </div>
            </div>
        </div>

        {{-- 포함 행정동 미리보기 --}}
        <div class="card-pad">
            <div class="flex items-center justify-between">
                <p class="text-[15px] font-extrabold text-ink-900">포함되는 행정동</p>
                <span x-show="loading" class="text-[12px] font-semibold text-brand-500">계산 중…</span>
            </div>

            <template x-if="mode === 'radius'">
                <div class="mt-4">
                    <div x-show="!preview.length && !loading"
                         class="rounded-xl border border-dashed border-line px-4 py-6 text-center text-[13px] text-ink-400">
                        중심 지점을 정하면 걸치는 행정동을 보여 드립니다.
                    </div>
                    <ul class="space-y-2.5">
                        <template x-for="item in preview" :key="item.code">
                            <li>
                                <div class="flex items-center justify-between gap-3 text-[13px]">
                                    <span class="truncate font-semibold text-ink-700" x-text="item.name"></span>
                                    <span class="shrink-0 tabular-nums text-ink-400" x-text="item.weight_percent + '%'"></span>
                                </div>
                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken">
                                    <div class="h-full rounded-full bg-brand-500" :style="`width: ${item.weight_percent}%`"></div>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <p x-show="preview.length" class="mt-4 text-[12px] leading-relaxed text-ink-400">
                        퍼센트는 그 행정동 면적 중 분석 원에 들어온 비율입니다. 통계는 이 비율만큼 안분해 합산합니다.
                    </p>
                </div>
            </template>

            <template x-if="mode === 'region'">
                <p class="mt-4 text-[13px] leading-relaxed text-ink-400">
                    행정동 분석은 선택한 동의 통계를 100% 그대로 합산합니다.
                </p>
            </template>
        </div>

        <button type="submit" class="btn-primary w-full py-3.5 text-[15px]"
                :disabled="!canSubmit()" x-bind:class="!canSubmit() && 'opacity-50'">
            상권분석 실행하기
        </button>

        <p class="text-center text-[12px] leading-relaxed text-ink-400">
            분석에는 보통 1초 내외가 걸립니다. 완료되면 리포트 화면으로 이동합니다.
        </p>
    </div>
</form>

<script>
function analysisForm(config) {
    return {
        mode: config.mode,
        radius: config.radius,
        period: config.period,
        period: config.period,
        center: { ...config.center },
        hasMapKey: config.hasMapKey,
        title: @js(old('title', '')),
        address: @js(old('address', '')),
        query: '',
        results: [],
        selected: [],
        preview: [],
        loading: false,
        map: null,
        marker: null,
        circle: null,

        init() {
            if (this.hasMapKey && window.kakao?.maps) {
                this.$nextTick(() => this.initMap());
            }
            this.refreshPreview();
        },

        get selectedCodes() {
            return this.mode === 'region' ? this.selected.map((r) => r.code) : [];
        },

        initMap() {
            const container = document.getElementById('map');
            if (!container) return;

            const position = new kakao.maps.LatLng(this.center.lat, this.center.lng);
            this.map = new kakao.maps.Map(container, { center: position, level: 5 });
            this.marker = new kakao.maps.Marker({ position, map: this.map });
            this.circle = new kakao.maps.Circle({
                center: position,
                radius: this.radius,
                strokeWeight: 2,
                strokeColor: '#0593ff',
                strokeOpacity: 0.9,
                fillColor: '#0593ff',
                fillOpacity: 0.12,
                map: this.map,
            });

            kakao.maps.event.addListener(this.map, 'click', (event) => {
                const latLng = event.latLng;
                this.center = { lat: latLng.getLat(), lng: latLng.getLng() };
                this.syncMap();
                this.refreshPreview();
            });
        },

        syncMap() {
            if (!this.map) return;
            const position = new kakao.maps.LatLng(this.center.lat, this.center.lng);
            this.marker.setPosition(position);
            this.circle.setPosition(position);
            this.circle.setRadius(this.radius);
            this.map.setCenter(position);
        },

        setMode(mode) {
            this.mode = mode;
            this.results = [];
            this.refreshPreview();
        },

        setRadius(value) {
            this.radius = value;
            this.syncMap();
            this.refreshPreview();
        },

        async search() {
            const keyword = this.query.trim();
            if (keyword.length < 1) {
                this.results = [];
                return;
            }

            const response = await fetch(`${config.searchUrl}?q=${encodeURIComponent(keyword)}`, {
                headers: { Accept: 'application/json' },
            });
            const json = await response.json();
            this.results = json.data ?? [];
        },

        pick(region) {
            this.results = [];
            this.query = '';

            if (this.mode === 'radius') {
                this.center = { lat: Number(region.lat), lng: Number(region.lng) };
                this.address = region.full_name;
                if (!this.title) this.title = `${region.full_name} 반경 ${this.radius.toLocaleString()}m`;
                this.syncMap();
                this.refreshPreview();
                return;
            }

            if (this.selected.some((item) => item.code === region.code)) return;
            this.selected.push(region);
            if (!this.title) this.title = `${region.full_name} 상권분석`;
        },

        remove(code) {
            this.selected = this.selected.filter((region) => region.code !== code);
        },

        async refreshPreview() {
            if (this.mode !== 'radius') {
                this.preview = [];
                return;
            }

            this.loading = true;

            try {
                const params = new URLSearchParams({
                    lat: this.center.lat,
                    lng: this.center.lng,
                    radius_m: this.radius,
                });
                const response = await fetch(`${config.previewUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                });
                const json = await response.json();
                this.preview = json.data ?? [];
            } catch (error) {
                this.preview = [];
            } finally {
                this.loading = false;
            }
        },

        canSubmit() {
            if (!this.title.trim()) return false;
            return this.mode === 'radius' ? this.preview.length > 0 : this.selected.length > 0;
        },
    };
}
</script>
@endsection
