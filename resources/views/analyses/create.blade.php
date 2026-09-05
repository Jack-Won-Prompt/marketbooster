@extends('layouts.app')

@section('title', '새 상권분석')
@section('heading', '새 상권분석')
@section('subheading', '분석할 지역을 고르면 리포트를 만들어 드립니다.')

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"
          integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"
            integrity="sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@endpush

@section('content')
<form method="POST" action="{{ route('analyses.store') }}"
      x-data="analysisForm({
          mode: @js(old('mode', 'radius')),
          radius: @js((int) old('radius_m', $defaultRadius)),
          period: @js(old('period', $defaultPeriod->code)),
          center: @js(['lat' => (float) old('center_lat', $defaultCenter['lat']), 'lng' => (float) old('center_lng', $defaultCenter['lng'])]),
          previewUrl: @js(route('api.regions.preview')),
          searchUrl: @js(route('api.regions.search')),
      })"
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

            <div class="mt-4 flex gap-2">
                <div class="relative flex-1">
                    <input type="text" x-model="query" @input.debounce.300ms="search()" @focus="search()"
                           @keydown.enter.prevent="submitSearch()"
                           class="input pl-10" placeholder="행정동 검색 (예: 가양1동, 의정부1동, 마곡)" autocomplete="off">
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

                {{-- 검색 버튼: 첫 번째 결과로 바로 이동한다 (목록을 고르지 않아도 되게) --}}
                <button type="button" @click="submitSearch()" :disabled="searching"
                        class="btn-primary shrink-0 px-5 disabled:opacity-60">
                    <span x-show="!searching">조회</span>
                    <span x-show="searching" x-cloak>…</span>
                </button>
            </div>

            <p x-show="searchError" x-cloak class="mt-2 text-[12px] font-semibold text-amber-700" x-text="searchError"></p>

            {{-- 지도 (Leaflet + OpenStreetMap — 별도 키가 필요 없다) --}}
            <div class="mt-4" x-show="mode === 'radius'" x-cloak>
                <div id="map" class="h-[340px] w-full overflow-hidden rounded-xl border border-line"></div>
                <p class="mt-2 text-[12px] text-ink-400">지도를 클릭해 분석 중심을 지정하세요.</p>
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
                <input id="title" name="title" x-model="title" @input="titleTouched = true"
                       required maxlength="120" class="input"
                       placeholder="예) 마곡나루역 반경 1km">
                <p x-show="!titleTouched && title" x-cloak class="mt-1.5 text-[12px] text-ink-400">
                    선택한 지역으로 자동으로 채웠습니다. 직접 고쳐도 됩니다.
                </p>
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
        title: @js(old('title', '')),
        address: @js(old('address', '')),
        query: '',
        results: [],
        searching: false,
        searchError: '',
        selected: [],
        preview: [],
        loading: false,
        // 사용자가 이름을 직접 고치면 그때부터 자동 생성을 멈춘다.
        titleTouched: @js(old('title', '') !== ''),
        map: null,
        marker: null,
        circle: null,
        nearestName: '',

        // Alpine 이 컴포넌트를 붙일 때 init() 을 자동으로 부른다. x-init 으로 또 부르면 지도가 두 번 만들어진다.
        init() {
            this.$nextTick(() => this.initMap());
            this.refreshPreview();
            this.applyAutoTitle();
        },

        /**
         * 분석 이름을 지금 선택 상태에 맞춰 채운다.
         * 사용자가 직접 고친 뒤에는 건드리지 않는다.
         */
        applyAutoTitle() {
            if (this.titleTouched) return;

            this.title = this.autoTitle();
        },

        autoTitle() {
            if (this.mode === 'region') {
                if (!this.selected.length) return '';

                const first = this.selected[0].full_name;

                return this.selected.length > 1
                    ? `${first} 외 ${this.selected.length - 1}곳 상권분석`
                    : `${first} 상권분석`;
            }

            const where = this.address || this.nearestName || '선택 지점';

            return `${where} 반경 ${this.radius.toLocaleString()}m`;
        },

        get selectedCodes() {
            return this.mode === 'region' ? this.selected.map((r) => r.code) : [];
        },

        initMap() {
            const container = document.getElementById('map');
            if (!container || this.map) return;

            const at = [this.center.lat, this.center.lng];

            this.map = L.map(container).setView(at, 14);

            L.tileLayer(@js(config('map.tile_url')), {
                maxZoom: 19,
                attribution: @js(config('map.tile_attribution')),
            }).addTo(this.map);

            this.marker = L.circleMarker(at, {
                radius: 7, color: '#ffffff', weight: 3, fillColor: '#0593ff', fillOpacity: 1,
            }).addTo(this.map);

            this.circle = L.circle(at, {
                radius: this.radius, color: '#0593ff', weight: 2, dashArray: '6 8',
                fillColor: '#0593ff', fillOpacity: 0.12,
            }).addTo(this.map);

            this.map.on('click', (event) => {
                this.center = { lat: event.latlng.lat, lng: event.latlng.lng };
                this.address = '';
                this.syncMap();
                this.refreshPreview();
                this.applyAutoTitle();
            });

            // 숨겨져 있다 나타나면 타일이 잘리므로 크기를 다시 잡는다.
            setTimeout(() => this.map.invalidateSize(), 200);
        },

        syncMap() {
            if (!this.map) return;

            const at = [this.center.lat, this.center.lng];

            this.marker.setLatLng(at);
            this.circle.setLatLng(at).setRadius(this.radius);
            this.map.fitBounds(this.circle.getBounds(), { padding: [30, 30], maxZoom: 16 });
        },

        setMode(mode) {
            this.mode = mode;
            this.results = [];
            this.searchError = '';
            // 숨겨져 있던 지도가 다시 보이면 타일이 잘리므로 크기를 다시 잡는다.
            if (mode === 'radius') this.$nextTick(() => this.map?.invalidateSize());
            this.refreshPreview();
            this.applyAutoTitle();
        },

        setRadius(value) {
            this.radius = value;
            this.syncMap();
            this.refreshPreview();
            this.applyAutoTitle();
        },

        async search() {
            const keyword = this.query.trim();
            this.searchError = '';

            if (keyword.length < 1) {
                this.results = [];
                return [];
            }

            const response = await fetch(`${config.searchUrl}?q=${encodeURIComponent(keyword)}`, {
                headers: { Accept: 'application/json' },
            });
            const json = await response.json();
            this.results = json.data ?? [];

            return this.results;
        },

        /**
         * 조회 버튼 / Enter — 목록을 고르지 않아도 가장 잘 맞는 곳으로 바로 이동한다.
         * 후보가 더 있으면 목록에 남겨 다른 곳을 고를 수 있게 한다.
         */
        async submitSearch() {
            if (this.searching) return;

            this.searching = true;

            try {
                const found = await this.search();

                if (!found.length) {
                    this.searchError = '검색 결과가 없습니다. 행정동·시군구 이름으로 찾아보세요.';
                    return;
                }

                const rest = found.slice(1);
                this.pick(found[0]);
                this.results = rest;
            } finally {
                this.searching = false;
            }
        },

        pick(region) {
            this.results = [];
            this.searchError = '';

            if (this.mode === 'radius') {
                this.query = region.full_name;
                this.center = { lat: Number(region.lat), lng: Number(region.lng) };
                this.address = region.full_name;
                this.syncMap();
                this.refreshPreview();
                this.applyAutoTitle();
                return;
            }

            this.query = '';

            if (this.selected.some((item) => item.code === region.code)) return;

            this.selected.push(region);
            this.applyAutoTitle();
        },

        remove(code) {
            this.selected = this.selected.filter((region) => region.code !== code);
            this.applyAutoTitle();
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

                // 지도를 직접 찍었을 때 이름에 쓸 대표 행정동
                if (!this.address) {
                    this.nearestName = this.preview[0]?.name ?? '';
                    this.applyAutoTitle();
                }
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
