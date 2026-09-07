@extends('layouts.app')

@section('title', '상권 보고서')
@section('heading', '상권 보고서')
@section('subheading', '지도에 상권을 직접 그리면 그 안의 통계를 보여 드립니다.')
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
<div class="flex h-[calc(100vh-4rem)] flex-col-reverse lg:flex-row"
     x-data="districtReport({
         center: @js($defaultCenter),
         radius: @js($defaultRadius),
         period: @js($defaultPeriod->code),
         maxArea: @js($maxAreaM2),
         groups: @js($groups),
         urls: {
             overview: @js(route('api.districts.overview')),
             stores: @js(route('api.districts.stores')),
             residence: @js(route('api.districts.residence')),
             search: @js(route('api.regions.search')),
             analyze: @js(route('analyses.store')),
         },
         csrf: @js(csrf_token()),
     })">

    {{-- ─── 왼쪽 패널 ────────────────────────────────────────── --}}
    <aside class="flex w-full shrink-0 flex-col border-t border-line-soft bg-white lg:h-full lg:w-[420px] lg:border-r lg:border-t-0">

        {{-- 상권이 없을 때 --}}
        <div x-show="!district" x-cloak class="flex flex-1 flex-col items-center justify-center p-8 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50">
                <svg class="h-7 w-7 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7l6-3 4 3 6-3v13l-6 3-4-3-6 3V7z"/>
                </svg>
            </div>
            <p class="mt-4 text-[15px] font-extrabold text-ink-900">상권을 그려 주세요</p>
            <p class="mt-2 text-[13px] leading-relaxed text-ink-400">
                지도 위 도구로 <strong>원형 · 사각형 · 다각형</strong> 중 하나를 골라<br>
                분석할 범위를 그리면 바로 계산합니다.
            </p>
            <p class="mt-3 text-[12px] text-ink-300">면적 {{ number_format($maxAreaM2) }}㎡ 이하로 만들 수 있습니다.</p>
        </div>

        {{-- 상권 머리말 --}}
        <template x-if="district">
            <div class="border-b border-line-soft px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[16px] font-extrabold text-ink-900" x-text="district.name"></p>
                        <p class="mt-0.5 truncate text-[12px] text-ink-400" x-text="district.address"></p>
                    </div>
                    <button type="button" @click="copyAddress()"
                            class="shrink-0 rounded-lg border border-line px-2.5 py-1.5 text-[11px] font-bold text-ink-500 transition hover:border-brand-400 hover:text-brand-600">
                        <span x-text="copied ? '복사됨' : '주소 복사'"></span>
                    </button>
                </div>
                <p class="mt-2 text-[12px] font-semibold text-brand-600" x-text="scopeLabel"></p>
            </div>
        </template>

        {{-- 탭 --}}
        <template x-if="district">
            <div class="flex border-b border-line-soft">
                <template x-for="t in tabs" :key="t.key">
                    <button type="button" @click="select(t.key)"
                            class="flex-1 border-b-2 px-3 py-3 text-[13px] font-bold transition"
                            :class="tab === t.key ? 'border-brand-500 text-brand-600' : 'border-transparent text-ink-400 hover:text-ink-700'"
                            x-text="t.label"></button>
                </template>
            </div>
        </template>

        {{-- 기간 --}}
        <template x-if="district">
            <div class="flex items-center justify-between gap-3 border-b border-line-soft px-5 py-2.5">
                <span class="text-[12px] font-bold text-ink-400">기준 기간</span>
                <select x-model="period" @change="reload()" class="input !w-auto !py-1.5 text-[13px]">
                    @foreach ($periods as $option)
                        <option value="{{ $option->code }}">{{ $option->label() }}</option>
                    @endforeach
                    @if (empty($periods))
                        <option value="{{ $defaultPeriod->code }}">{{ $defaultPeriod->label() }}</option>
                    @endif
                </select>
            </div>
        </template>

        {{-- 로딩 --}}
        <div x-show="loading" x-cloak class="flex flex-1 items-center justify-center p-10">
            <div class="text-center">
                <svg class="mx-auto h-8 w-8 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                </svg>
                <p class="mt-3 text-[13px] font-semibold text-ink-700">상권 생성 중</p>
                <p class="mt-1 text-[12px] text-ink-400">설정한 상권의 정보를 불러오고 있습니다</p>
            </div>
        </div>

        {{-- 오류 --}}
        <div x-show="error" x-cloak class="m-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="text-[13px] font-extrabold text-amber-900" x-text="error"></p>
        </div>

        {{-- 내용 --}}
        <div x-show="district && !loading" x-cloak class="flex-1 overflow-y-auto">

            {{-- ─── 상권 탭 ─────────────────────────────────── --}}
            <div x-show="tab === 'overview'" class="divide-y divide-line-soft">

                {{-- 추정 매출 --}}
                <section class="px-5 py-4">
                    <p class="text-[13px] font-extrabold text-ink-900" x-text="`${periodLabel} 상권 추정 매출`"></p>

                    <template x-if="covered('sales')">
                        <div>
                            <p class="stat-value mt-2" x-text="money(overview?.sales?.monthly_amount)"></p>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <template x-for="c in salesCards" :key="c.label">
                                    <div class="rounded-lg bg-surface-muted px-3 py-2.5">
                                        <p class="text-[10.5px] font-semibold text-ink-400" x-text="c.label"></p>
                                        <p class="mt-0.5 text-[14px] font-extrabold tabular-nums text-ink-900" x-text="c.value"></p>
                                    </div>
                                </template>
                            </div>
                            <p class="mt-3 text-[11px] leading-relaxed text-ink-400">
                                * 하루 평균 매출에 30일을 곱한 월 환산값입니다. 카드매출 공개 통계는 행정동 단위라
                                상권에 걸치는 면적 비율만큼 안분했습니다.
                            </p>
                        </div>
                    </template>

                    <template x-if="!covered('sales')">
                        <p class="mt-3 rounded-xl border border-dashed border-line px-4 py-5 text-center text-[12px] leading-relaxed text-ink-400"
                           x-text="`${district?.sido_name ?? '이 지역'}은(는) 카드매출을 행정동 단위로 공개하는 출처가 없어 비워 두었습니다.`"></p>
                    </template>
                </section>

                {{-- 매출 변화 --}}
                <section class="px-5 py-4" x-show="covered('sales')">
                    <p class="text-[13px] font-extrabold text-ink-900">기간별 매출 변화</p>

                    <template x-if="(overview?.trend ?? []).length > 1">
                        <ul class="mt-3 space-y-2">
                            <template x-for="t in overview.trend" :key="t.code">
                                <li>
                                    <div class="flex items-center justify-between text-[12px]">
                                        <span class="font-semibold text-ink-700" x-text="t.label"></span>
                                        <span class="tabular-nums text-ink-400" x-text="money(t.amount * 30)"></span>
                                    </div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-sunken">
                                        <div class="h-full rounded-full bg-brand-500 transition-[width] duration-500"
                                             :style="{ width: trendPct(t) + '%' }"></div>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </template>

                    <p x-show="(overview?.trend ?? []).length <= 1" class="mt-3 text-[12px] leading-relaxed text-ink-400">
                        수록된 기간이 하나뿐이라 변화를 그릴 수 없습니다.
                        <code class="rounded bg-surface-muted px-1.5 py-0.5">php artisan seoul:sync card_sales --yq=…</code>
                        로 이전 분기를 더 모으면 나타납니다.
                    </p>
                </section>

                {{-- 상권 매장 --}}
                <section class="px-5 py-4">
                    <div class="flex items-baseline justify-between">
                        <p class="text-[13px] font-extrabold text-ink-900" x-text="`${periodLabel} 상권 매장`"></p>
                        <span class="text-[11px] text-ink-300"
                              x-text="`${(overview?.stores?.total ?? 0).toLocaleString()}개`"></span>
                    </div>

                    <p class="mt-1 text-[11px] leading-relaxed text-ink-400">
                        * 매장 수는 상권 안에 좌표가 있는 실제 점포를 센 값이고,
                        매출은 행정동 카드매출을 면적 비율로 안분한 값이라 근거가 서로 다릅니다.
                    </p>

                    <ul class="mt-3 space-y-1.5">
                        <template x-for="g in (overview?.stores?.groups ?? [])" :key="g.code">
                            <li>
                                <button type="button" @click="openStores(g.code)"
                                        class="flex w-full items-center justify-between gap-3 rounded-lg border border-line-soft px-3.5 py-2.5 text-left transition hover:border-brand-400">
                                    <span class="min-w-0">
                                        <span class="block text-[13px] font-semibold text-ink-700"
                                              x-text="`${g.name} ${g.stores.toLocaleString()}개 매장`"></span>
                                        <span x-show="covered('sales') && g.monthly_amount > 0"
                                              class="mt-0.5 block text-[11.5px] tabular-nums text-ink-400"
                                              x-text="`월 추정 매출 ${money(g.monthly_amount)}`"></span>
                                    </span>
                                    <span class="shrink-0 text-[11px] font-bold text-brand-600">보기</span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </section>

                {{-- 결제 경향 --}}
                <section class="px-5 py-4">
                    <p class="text-[13px] font-extrabold text-ink-900" x-text="`${periodLabel} 상권 결제 경향`"></p>

                    <template x-if="overview?.payment_habits?.covered">
                        <div class="mt-3 space-y-3">
                            <template x-for="h in overview.payment_habits.items" :key="h.key">
                                <div class="rounded-xl border border-line-soft px-3.5 py-3">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <p class="text-[12px] font-semibold text-ink-400" x-text="h.title"></p>
                                        <p class="text-[13px] font-extrabold text-brand-600" x-text="h.top"></p>
                                    </div>
                                    <ul class="mt-2.5 space-y-1.5">
                                        <template x-for="b in h.breakdown.slice(0, 5)" :key="b.label">
                                            <li>
                                                <div class="flex items-center justify-between text-[11.5px]">
                                                    <span class="text-ink-600" x-text="b.label"></span>
                                                    <span class="tabular-nums text-ink-400" x-text="b.share + '%'"></span>
                                                </div>
                                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-sunken">
                                                    <div class="h-full rounded-full bg-brand-400" :style="{ width: b.share + '%' }"></div>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                            <p class="text-[11px] leading-relaxed text-ink-400">
                                * 결제 발생 일별(휴일)과 결제 세대(유자녀) 축은 서울시 상권분석서비스가 제공하지 않아 만들지 않았습니다.
                            </p>
                        </div>
                    </template>

                    <p x-show="!overview?.payment_habits?.covered" x-cloak
                       class="mt-3 rounded-xl border border-dashed border-line px-4 py-5 text-center text-[12px] text-ink-400">
                        카드매출이 수록되지 않아 결제 경향을 계산할 수 없습니다.
                    </p>
                </section>

                {{-- 리포트로 저장 --}}
                <section class="px-5 py-4">
                    <form method="POST" :action="config.urls.analyze">
                        <input type="hidden" name="_token" :value="config.csrf">
                        <input type="hidden" name="mode" value="polygon">
                        <input type="hidden" name="shape_kind" :value="shape">
                        <input type="hidden" name="shape_ring" :value="JSON.stringify(ring)">
                        <input type="hidden" name="area_m2" :value="district?.area_m2 ?? 0">
                        <input type="hidden" name="center_lat" :value="district?.center?.lat ?? ''">
                        <input type="hidden" name="center_lng" :value="district?.center?.lng ?? ''">
                        <input type="hidden" name="radius_m" :value="district?.radius_m ?? ''">
                        <input type="hidden" name="address" :value="district?.address ?? ''">
                        <input type="hidden" name="period" :value="period">
                        <input type="hidden" name="title" :value="district?.name ?? '새 상권'">
                        <button class="btn-primary w-full">이 상권으로 리포트 만들기</button>
                    </form>
                    <p class="mt-2 text-center text-[11px] text-ink-400">
                        리포트를 만들면 PDF · 프랜차이즈 CSV 로 내려받을 수 있습니다.
                    </p>
                </section>
            </div>

            {{-- ─── 매장 탭 ─────────────────────────────────── --}}
            <div x-show="tab === 'stores'" class="flex h-full flex-col">
                <div class="border-b border-line-soft px-5 py-3">
                    <div class="relative">
                        <input type="text" x-model="storeQuery" @input.debounce.400ms="loadStores(1)"
                               class="input pl-9 text-[13px]" placeholder="매장 이름 검색" autocomplete="off">
                        <svg class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-ink-300" fill="none"
                             stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/>
                        </svg>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <template x-for="g in storeFilters" :key="g.code">
                            <button type="button" @click="setGroup(g.code)"
                                    class="rounded-full border px-3 py-1.5 text-[12px] font-bold transition"
                                    :class="storeGroup === g.code
                                        ? 'border-brand-500 bg-brand-50 text-brand-600'
                                        : 'border-line text-ink-500 hover:border-brand-400'">
                                <span x-text="g.name"></span>
                                <span class="ml-1 tabular-nums opacity-70" x-text="(stores?.counts?.[g.code] ?? 0).toLocaleString()"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <ul class="flex-1 divide-y divide-line-soft overflow-y-auto">
                    <template x-for="s in (stores?.items ?? [])" :key="s.id">
                        <li class="px-5 py-3">
                            <button type="button" @click="focusStore(s)" class="w-full text-left">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-[13px] font-semibold text-ink-900" x-text="s.name"></p>
                                        <p class="mt-0.5 text-[11.5px] text-ink-400">
                                            <span x-text="s.industry"></span>
                                            <template x-if="s.brand">
                                                <span class="ml-1.5 rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-bold text-brand-600"
                                                      x-text="s.brand"></span>
                                            </template>
                                        </p>
                                        <p class="mt-0.5 truncate text-[11px] text-ink-300" x-text="s.address"></p>
                                    </div>
                                    <span class="shrink-0 text-[11px] font-bold text-brand-600">보기</span>
                                </div>
                            </button>
                        </li>
                    </template>

                    <li x-show="!(stores?.items ?? []).length" class="px-5 py-10 text-center text-[13px] text-ink-400">
                        조건에 맞는 매장이 없습니다.
                    </li>
                </ul>

                <div class="flex items-center justify-between border-t border-line-soft px-5 py-3">
                    <span class="text-[12px] tabular-nums text-ink-400"
                          x-text="`${stores?.page ?? 1}/${stores?.pages ?? 1}`"></span>
                    <div class="flex gap-1">
                        <button type="button" @click="loadStores(1)" :disabled="(stores?.page ?? 1) <= 1"
                                class="rounded-lg border border-line px-2.5 py-1.5 text-[12px] font-bold text-ink-500 disabled:opacity-40">처음</button>
                        <button type="button" @click="loadStores((stores?.page ?? 1) - 1)" :disabled="(stores?.page ?? 1) <= 1"
                                class="rounded-lg border border-line px-2.5 py-1.5 text-[12px] font-bold text-ink-500 disabled:opacity-40">이전</button>
                        <button type="button" @click="loadStores((stores?.page ?? 1) + 1)" :disabled="(stores?.page ?? 1) >= (stores?.pages ?? 1)"
                                class="rounded-lg border border-line px-2.5 py-1.5 text-[12px] font-bold text-ink-500 disabled:opacity-40">다음</button>
                        <button type="button" @click="loadStores(stores?.pages ?? 1)" :disabled="(stores?.page ?? 1) >= (stores?.pages ?? 1)"
                                class="rounded-lg border border-line px-2.5 py-1.5 text-[12px] font-bold text-ink-500 disabled:opacity-40">마지막</button>
                    </div>
                </div>
            </div>

            {{-- ─── 주거인구 탭 ─────────────────────────────── --}}
            <div x-show="tab === 'residence'" class="divide-y divide-line-soft">
                <section class="px-5 py-4">
                    <p class="text-[13px] font-extrabold text-ink-900" x-text="`${periodLabel} 주거인구`"></p>

                    <template x-if="residenceCovered('resident')">
                        <div>
                            <p class="stat-value mt-2"
                               x-text="`약 ${(residence?.resident?.total ?? 0).toLocaleString()}명`"></p>
                            <p class="mt-1 text-[12px] text-ink-500"
                               x-text="residence?.resident?.top_label ? `${residence.resident.top_label} 주거인구 비율이 가장 높아요.` : ''"></p>

                            <ul class="mt-3 space-y-1.5">
                                <template x-for="row in residentBars" :key="row.label">
                                    <li>
                                        <div class="flex items-center justify-between text-[11.5px]">
                                            <span class="text-ink-600" x-text="row.label"></span>
                                            <span class="tabular-nums text-ink-400" x-text="row.value.toLocaleString()"></span>
                                        </div>
                                        <div class="mt-1 flex gap-1">
                                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-sunken">
                                                <div class="h-full rounded-full bg-brand-500" :style="{ width: row.malePct + '%' }"></div>
                                            </div>
                                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-sunken">
                                                <div class="h-full rounded-full" style="background-color:#f2557b"
                                                     :style="{ width: row.femalePct + '%' }"></div>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                            <div class="mt-2 flex gap-4 text-[11px] text-ink-400">
                                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand-500"></span>남성</span>
                                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background:#f2557b"></span>여성</span>
                            </div>
                        </div>
                    </template>

                    <p x-show="!residenceCovered('resident')" x-cloak
                       class="mt-3 rounded-xl border border-dashed border-line px-4 py-5 text-center text-[12px] text-ink-400">
                        이 지역은 주거인구가 아직 수록되지 않았습니다.
                    </p>
                </section>

                <section class="px-5 py-4">
                    <p class="text-[13px] font-extrabold text-ink-900">배후세대</p>

                    <template x-if="residenceCovered('households')">
                        <div>
                            <p class="stat-value mt-2"
                               x-text="`약 ${(residence?.households?.total ?? 0).toLocaleString()}세대`"></p>
                            <p class="mt-1 text-[12px] text-ink-500"
                               x-text="`아파트 약 ${(residence?.households?.apartment ?? 0).toLocaleString()}세대 · 전체의 ${residence?.households?.apartment_share ?? 0}%`"></p>

                            <ul class="mt-3 space-y-1.5">
                                <template x-for="row in housingBars" :key="row.label">
                                    <li>
                                        <div class="flex items-center justify-between text-[11.5px]">
                                            <span class="text-ink-600" x-text="row.label"></span>
                                            <span class="tabular-nums text-ink-400" x-text="row.value.toLocaleString()"></span>
                                        </div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-sunken">
                                            <div class="h-full rounded-full bg-brand-500" :style="{ width: row.pct + '%' }"></div>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <p x-show="!residenceCovered('households')" x-cloak
                       class="mt-3 rounded-xl border border-dashed border-line px-4 py-5 text-center text-[12px] text-ink-400">
                        이 지역은 배후세대가 아직 수록되지 않았습니다.
                    </p>
                </section>

                <section class="px-5 py-4">
                    <p class="text-[13px] font-extrabold text-ink-900">입주 예정 아파트 (3년 이내)</p>

                    <ul class="mt-3 space-y-2" x-show="(residence?.households?.move_ins ?? []).length">
                        <template x-for="m in (residence?.households?.move_ins ?? [])" :key="m.complex_name + m.move_in_ym">
                            <li class="flex items-center justify-between gap-3 text-[12px]">
                                <span class="truncate font-semibold text-ink-700" x-text="m.complex_name"></span>
                                <span class="shrink-0 tabular-nums text-ink-400"
                                      x-text="`${m.households.toLocaleString()}세대 · ${m.move_in_ym.slice(0,4)}.${m.move_in_ym.slice(4,6)}`"></span>
                            </li>
                        </template>
                    </ul>

                    <p x-show="!(residence?.households?.move_ins ?? []).length" x-cloak
                       class="mt-3 text-[12px] text-ink-400">3년 이내 입주예정인 단지가 조회되지 않습니다.</p>
                </section>

                <section class="px-5 py-4">
                    <p class="text-[12px] font-bold text-ink-500">아직 수록하지 못한 항목</p>
                    <ul class="mt-2 space-y-1 text-[11.5px] leading-relaxed text-ink-400">
                        <li>· 1인 가구 — 행정동 단위 공개 출처를 아직 확보하지 못했습니다.</li>
                        <li>· 아파트 3.3㎡당 실거래가 · 매매 거래량 — 국토교통부 실거래가 API 활용신청이 필요합니다.</li>
                    </ul>
                </section>
            </div>
        </div>
    </aside>

    {{-- ─── 지도 ─────────────────────────────────────────────── --}}
    <div class="relative h-[52vh] flex-1 lg:h-auto">
        <div id="district-map" class="absolute inset-0 z-0"></div>

        {{-- 검색 --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 z-[500] p-4">
            <div class="pointer-events-auto mx-auto flex w-full max-w-lg gap-2">
                <div class="relative flex-1">
                    <input type="text" x-model="query" @keydown.enter.prevent="submitSearch()"
                           class="input pl-10 shadow-lg" placeholder="행정동으로 이동 (예: 가양1동, 의정부1동)" autocomplete="off">
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
                <button type="button" @click="submitSearch()" class="btn-primary shrink-0 px-5 shadow-lg">검색</button>
            </div>
        </div>

        {{-- 상권 만들기 도구 --}}
        <div class="absolute left-4 top-24 z-[500] flex flex-col gap-2">
            <div class="flex flex-col gap-1 rounded-xl border border-line-soft bg-white p-1.5 shadow-lg">
                <template x-for="t in shapeTools" :key="t.kind">
                    <button type="button" @click="startDrawing(t.kind)"
                            class="flex w-[72px] flex-col items-center gap-1 rounded-lg px-2 py-2 transition"
                            :class="drawing === t.kind ? 'bg-brand-50 text-brand-600' : 'text-ink-500 hover:bg-surface-muted'">
                        <span class="text-[18px] leading-none" x-text="t.icon"></span>
                        <span class="text-[11px] font-bold" x-text="t.label"></span>
                    </button>
                </template>
                <button type="button" @click="showHelp = !showHelp"
                        class="flex w-[72px] flex-col items-center gap-1 rounded-lg px-2 py-2 text-ink-400 transition hover:bg-surface-muted">
                    <span class="text-[18px] leading-none">?</span>
                    <span class="text-[11px] font-bold">도움말</span>
                </button>
            </div>

            <button type="button" x-show="district" x-cloak @click="clearDistrict()"
                    class="rounded-xl border border-line-soft bg-white px-3 py-2 text-[12px] font-bold text-ink-500 shadow-lg transition hover:border-brand-400 hover:text-brand-600">
                다시 그리기
            </button>
        </div>

        {{-- 도움말 --}}
        <div x-show="showHelp" x-cloak
             class="absolute left-[104px] top-24 z-[600] w-[280px] rounded-xl border border-line-soft bg-white p-4 shadow-xl">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[13px] font-extrabold text-ink-900">상권 만들기</p>
                <button type="button" @click="showHelp = false" class="text-ink-300 hover:text-ink-600">&times;</button>
            </div>
            <ul class="mt-2.5 space-y-1.5 text-[12px] leading-relaxed text-ink-500">
                <li><strong class="text-ink-700">원형</strong> — 중심을 누른 채 끌면 반경이 정해집니다.</li>
                <li><strong class="text-ink-700">사각형</strong> — 한쪽 모서리에서 반대쪽 모서리까지 끕니다.</li>
                <li><strong class="text-ink-700">다각형</strong> — 꼭짓점을 차례로 찍고, 마지막에 더블클릭하면 닫힙니다.</li>
            </ul>
            <p class="mt-2.5 border-t border-line-soft pt-2.5 text-[11.5px] text-ink-400">
                면적 {{ number_format($maxAreaM2) }}㎡ 이하로 만들 수 있습니다.
                모든 통계는 상권에 걸치는 행정동 면적 비율만큼 안분해 합산합니다.
            </p>
        </div>

        {{-- 그리는 중 안내 --}}
        <div x-show="drawing" x-cloak
             class="pointer-events-none absolute inset-x-0 bottom-6 z-[500] flex justify-center">
            <p class="rounded-full bg-ink-900/85 px-5 py-2.5 text-[13px] font-semibold text-white shadow-lg"
               x-text="drawHint"></p>
        </div>
    </div>
</div>

<script>
function districtReport(config) {
    return {
        config,
        period: config.period,
        map: null,
        layer: null,
        storeLayer: null,
        drawing: null,
        showHelp: false,
        ring: [],
        shape: null,
        radiusM: null,
        district: null,
        overview: null,
        stores: null,
        residence: null,
        tab: 'overview',
        loading: false,
        error: '',
        copied: false,
        query: '',
        results: [],
        storeGroup: 'all',
        storeQuery: '',

        tabs: [
            { key: 'overview', label: '상권' },
            { key: 'stores', label: '매장' },
            { key: 'residence', label: '주거인구' },
        ],

        shapeTools: [
            { kind: 'circle', label: '원형', icon: '◯' },
            { kind: 'rectangle', label: '사각형', icon: '▭' },
            { kind: 'polygon', label: '다각형', icon: '⬠' },
        ],

        get storeFilters() {
            return [{ code: 'all', name: '전체' }, ...config.groups];
        },

        get periodLabel() {
            return this.district?.period?.label ?? '';
        },

        get scopeLabel() {
            if (!this.district) return '';
            const parts = [this.district.shape_label];
            if (this.district.radius_m) parts.push(`반경 ${this.district.radius_m.toLocaleString()}m`);
            parts.push(`${this.district.area_m2.toLocaleString()}㎡`);
            return parts.join(' / ');
        },

        get drawHint() {
            if (this.drawing === 'circle') return '중심을 누른 채 끌어 반경을 정하세요';
            if (this.drawing === 'rectangle') return '모서리에서 반대쪽 모서리까지 끌어 주세요';
            if (this.drawing === 'polygon') return '꼭짓점을 차례로 찍고 더블클릭하면 닫힙니다';
            return '';
        },

        get salesCards() {
            const s = this.overview?.sales;
            if (!s) return [];
            return [
                { label: '일평균 매출', value: this.money(s.daily_amount) },
                { label: '일평균 건수', value: (s.daily_count ?? 0).toLocaleString() + '건' },
                { label: '건당 평균', value: (s.avg_ticket ?? 0).toLocaleString() + '원' },
            ];
        },

        get residentBars() {
            const matrix = this.residence?.resident?.matrix;
            if (!matrix) return [];
            const labels = {
                under10: '10세 미만', '10s': '10대', '20s': '20대', '30s': '30대',
                '40s': '40대', '50s': '50대', '60s': '60대', '70s_over': '70대 이상',
            };
            const rows = Object.keys(labels).map((band) => {
                const male = matrix.M?.[band] ?? 0;
                const female = matrix.F?.[band] ?? 0;
                return { label: labels[band], male, female, value: male + female };
            });
            const max = Math.max(1, ...rows.map((r) => Math.max(r.male, r.female)));
            return rows.map((r) => ({
                ...r,
                malePct: Math.round((r.male / max) * 100),
                femalePct: Math.round((r.female / max) * 100),
            }));
        },

        get housingBars() {
            const byType = this.residence?.households?.by_type;
            if (!byType) return [];
            const labels = {
                apartment: '아파트', non_apartment: '비아파트', detached: '단독주택',
                villa: '연립·다세대', officetel: '오피스텔',
            };
            const rows = Object.entries(byType)
                .filter(([, v]) => v > 0)
                .map(([k, v]) => ({ label: labels[k] ?? k, value: v }));
            const max = Math.max(1, ...rows.map((r) => r.value));
            return rows.map((r) => ({ ...r, pct: Math.round((r.value / max) * 100) }));
        },

        init() {
            this.$nextTick(() => this.initMap());
        },

        initMap() {
            const container = document.getElementById('district-map');
            if (!container || this.map) return;

            this.map = L.map(container, { doubleClickZoom: false })
                .setView([config.center.lat, config.center.lng], 15);

            L.tileLayer(@js(config('map.tile_url')), {
                maxZoom: 19,
                attribution: @js(config('map.tile_attribution')),
            }).addTo(this.map);

            this.storeLayer = L.layerGroup().addTo(this.map);
            this.bindDrawing();
            setTimeout(() => this.map.invalidateSize(), 200);
        },

        /**
         * 그리기는 Leaflet 기본 이벤트만으로 만든다.
         * 원·사각형은 누른 채 끌기, 다각형은 클릭으로 꼭짓점을 찍고 더블클릭으로 닫는다.
         */
        bindDrawing() {
            let anchor = null;
            let preview = null;
            let points = [];

            const clearPreview = () => {
                if (preview) { this.map.removeLayer(preview); preview = null; }
            };

            this.map.on('mousedown', (e) => {
                if (this.drawing !== 'circle' && this.drawing !== 'rectangle') return;
                anchor = e.latlng;
                this.map.dragging.disable();
            });

            this.map.on('mousemove', (e) => {
                if (!anchor) return;
                clearPreview();
                preview = this.drawing === 'circle'
                    ? L.circle(anchor, { radius: anchor.distanceTo(e.latlng), ...this.shapeStyle() })
                    : L.rectangle(L.latLngBounds(anchor, e.latlng), this.shapeStyle());
                preview.addTo(this.map);
            });

            this.map.on('mouseup', (e) => {
                if (!anchor) return;
                const kind = this.drawing;
                const start = anchor;
                anchor = null;
                this.map.dragging.enable();
                clearPreview();

                if (kind === 'circle') {
                    const radius = Math.round(start.distanceTo(e.latlng));
                    if (radius < 50) return;
                    this.applyShape('circle', this.circleRing(start, radius), radius);
                } else {
                    const b = L.latLngBounds(start, e.latlng);
                    if (b.getNorthEast().distanceTo(b.getSouthWest()) < 70) return;
                    this.applyShape('rectangle', [
                        [b.getWest(), b.getSouth()],
                        [b.getEast(), b.getSouth()],
                        [b.getEast(), b.getNorth()],
                        [b.getWest(), b.getNorth()],
                    ]);
                }
            });

            this.map.on('click', (e) => {
                if (this.drawing !== 'polygon') return;
                points.push([e.latlng.lng, e.latlng.lat]);
                clearPreview();
                preview = L.polygon(points.map(([lng, lat]) => [lat, lng]), this.shapeStyle()).addTo(this.map);
            });

            this.map.on('dblclick', () => {
                if (this.drawing !== 'polygon' || points.length < 3) return;
                const ring = points.slice();
                points = [];
                clearPreview();
                this.applyShape('polygon', ring);
            });
        },

        shapeStyle() {
            return { color: '#0593ff', weight: 2, fillColor: '#0593ff', fillOpacity: 0.15 };
        },

        circleRing(center, radiusM, segments = 64) {
            const ring = [];
            const latPerKm = 1 / 110.574;
            const lngPerKm = 1 / (111.32 * Math.cos((center.lat * Math.PI) / 180));
            const km = radiusM / 1000;
            for (let i = 0; i < segments; i++) {
                const a = (2 * Math.PI * i) / segments;
                ring.push([
                    center.lng + Math.cos(a) * km * lngPerKm,
                    center.lat + Math.sin(a) * km * latPerKm,
                ]);
            }
            return ring;
        },

        startDrawing(kind) {
            this.drawing = this.drawing === kind ? null : kind;
            this.showHelp = false;
        },

        applyShape(kind, ring, radiusM = null) {
            this.drawing = null;
            this.shape = kind;
            this.ring = ring.map(([lng, lat]) => [Number(lng.toFixed(6)), Number(lat.toFixed(6))]);
            this.radiusM = radiusM;

            if (this.layer) this.map.removeLayer(this.layer);
            this.layer = L.polygon(this.ring.map(([lng, lat]) => [lat, lng]), this.shapeStyle()).addTo(this.map);
            this.map.fitBounds(this.layer.getBounds(), { padding: [40, 40] });

            this.tab = 'overview';
            this.reload();
        },

        clearDistrict() {
            if (this.layer) { this.map.removeLayer(this.layer); this.layer = null; }
            this.storeLayer.clearLayers();
            this.district = this.overview = this.stores = this.residence = null;
            this.ring = [];
            this.shape = null;
            this.error = '';
        },

        payload(extra = {}) {
            return {
                shape: this.shape,
                ring: this.ring,
                radius_m: this.radiusM,
                period: this.period,
                ...extra,
            };
        },

        async post(url, extra = {}) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify(this.payload(extra)),
            });
            return res.json();
        },

        select(tab) {
            this.tab = tab;
            if (tab === 'stores' && !this.stores) this.loadStores(1);
            if (tab === 'residence' && !this.residence) this.loadResidence();
        },

        openStores(group) {
            this.storeGroup = group;
            this.tab = 'stores';
            this.loadStores(1);
        },

        setGroup(group) {
            this.storeGroup = group;
            this.loadStores(1);
        },

        async reload() {
            if (!this.ring.length) return;
            this.loading = true;
            this.error = '';
            this.stores = null;
            this.residence = null;

            try {
                const json = await this.post(config.urls.overview);
                if (!json.ok) { this.error = json.message ?? '상권을 계산하지 못했습니다.'; this.district = null; return; }
                this.district = json.district;
                this.overview = json.overview;
            } catch (e) {
                this.error = '상권을 계산하지 못했습니다.';
            } finally {
                this.loading = false;
            }
        },

        async loadStores(page) {
            if (!this.ring.length) return;
            const json = await this.post(config.urls.stores, {
                group: this.storeGroup === 'all' ? null : this.storeGroup,
                q: this.storeQuery,
                page: Math.max(1, page || 1),
            });
            if (json.ok) this.stores = json.stores;
        },

        async loadResidence() {
            if (!this.ring.length) return;
            const json = await this.post(config.urls.residence);
            if (json.ok) this.residence = json.residence;
        },

        focusStore(store) {
            if (!store.lat || !store.lng) return;
            this.storeLayer.clearLayers();
            L.circleMarker([store.lat, store.lng], {
                radius: 7, color: '#ffffff', weight: 3, fillColor: '#0593ff', fillOpacity: 1,
            }).addTo(this.storeLayer).bindPopup(`<strong>${store.name}</strong><br>${store.industry ?? ''}`).openPopup();
            this.map.panTo([store.lat, store.lng]);
        },

        covered(key) {
            const c = this.overview?.coverage;
            return !c || c[key] !== false;
        },

        residenceCovered(key) {
            const c = this.residence?.coverage;
            return !c || c[key] !== false;
        },

        trendPct(point) {
            const max = Math.max(1, ...(this.overview?.trend ?? []).map((t) => t.amount));
            return Math.max(3, Math.round((point.amount / max) * 100));
        },

        async submitSearch() {
            const keyword = this.query.trim();
            if (!keyword) { this.results = []; return; }
            const res = await fetch(`${config.urls.search}?q=${encodeURIComponent(keyword)}`, {
                headers: { Accept: 'application/json' },
            });
            const found = (await res.json()).data ?? [];
            this.results = found.slice(1);
            if (found[0]) this.goTo(found[0]);
        },

        goTo(region) {
            this.results = [];
            this.query = region.full_name;
            this.map.setView([Number(region.lat), Number(region.lng)], 15);
        },

        async copyAddress() {
            try {
                await navigator.clipboard.writeText(this.district?.address ?? '');
                this.copied = true;
                setTimeout(() => (this.copied = false), 1500);
            } catch (e) {
                this.copied = false;
            }
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
