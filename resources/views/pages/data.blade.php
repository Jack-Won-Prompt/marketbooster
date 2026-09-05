@extends('layouts.site')

@section('title', '데이터')

@section('content')
<section class="relative overflow-hidden border-b border-line-soft bg-white">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="blob -left-24 top-[-140px] h-[380px] w-[380px] bg-brand-100"></div>
        <div class="blob right-[-120px] top-[-60px] h-[340px] w-[340px] bg-accent-mint/20" style="animation-delay: -8s"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-white/40 to-white"></div>
    </div>
    <div class="container-page relative py-20 lg:py-24">
        <p class="eyebrow" data-reveal>Data</p>
        <h1 class="section-title mt-4 max-w-3xl" data-reveal data-reveal-delay="60">공개된 데이터를, 같은 기준으로</h1>
        <p class="mt-5 max-w-2xl text-[16px] leading-relaxed text-ink-500" data-reveal data-reveal-delay="130">
            기관마다 코드 체계와 파일 형식이 다릅니다. MarketScope는 이를 행정동코드와 기준연월,
            표준 성별·연령·시간대 코드로 정규화해 하나의 데이터 모델에 담습니다.
        </p>
    </div>
</section>

<section class="container-page py-20">
    <h2 class="text-[22px] font-extrabold text-ink-900" data-reveal>수집하는 데이터</h2>

    <div class="mt-8 overflow-x-auto" data-reveal>
        <table class="table-report">
            <thead>
                <tr>
                    <th>데이터</th><th>출처</th><th>집계 단위</th><th>교차 축</th>
                </tr>
            </thead>
            <tbody>
                @foreach ([
                    ['유동인구', '공공데이터포털 지역별 유동인구 통계', '행정동 · 월', '요일 · 시간대 · 성별 · 연령'],
                    ['카드매출', '공공데이터포털 지역별 카드매출 통계', '행정동 · 월', '업종 · 요일 · 시간대 / 성별 · 연령'],
                    ['거주 인구(추정)', '행정안전부 주민등록인구', '행정동 · 월', '성별 · 연령'],
                    ['배후세대', '국토교통부 건축물대장', '행정동 · 월', '주거유형(아파트/오피스텔/빌라/단독)'],
                    ['직장인구', '통계청 전국사업체조사', '행정동 · 연', '성별 · 연령'],
                    ['학생 수', '한국교육학술정보원 학교알리미', '행정동 · 월', '학교급'],
                    ['학원 수', '지방행정인허가데이터 학원교습소', '행정동 · 월', '교육/입시 · 예체능 · 상세업종'],
                    ['행정동 경계', '행정안전부 행정동 경계 · 중심점', '행정동', '중심좌표 · 면적 · 폴리곤'],
                ] as [$label, $provider, $grain, $axis])
                    <tr>
                        <td class="font-bold text-ink-900">{{ $label }}</td>
                        <td>{{ $provider }}</td>
                        <td class="text-ink-500">{{ $grain }}</td>
                        <td class="text-ink-500">{{ $axis }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-16 grid gap-6 lg:grid-cols-3" data-reveal-stagger="110">
        @foreach ([
            ['정규화', '기관마다 다른 필드명과 코드값(성별 1/2, M/F, 남/여 …)을 내부 표준으로 변환합니다.'],
            ['면적 안분', '반경이 행정동 경계를 가로지르면 겹친 면적 비율만큼만 통계를 반영합니다.'],
            ['재수집 안전', '같은 행정동·기준월·교차축 조합은 유일 키로 관리되어 재수집 시 갱신됩니다.'],
        ] as [$title, $desc])
            <div class="card-pad lift">
                <p class="text-[16px] font-extrabold text-ink-900">{{ $title }}</p>
                <p class="mt-3 text-[14px] leading-relaxed text-ink-500">{{ $desc }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-16 rounded-2xl border border-line bg-surface-muted p-8" data-reveal>
        <p class="text-[16px] font-extrabold text-ink-900">데이터 이용에 관한 안내</p>
        <p class="mt-3 max-w-3xl text-[14px] leading-relaxed text-ink-500">
            모든 통계는 집계 방법과 기준일, 분석 방법에 따라 오차가 발생할 수 있으므로 참고용으로 활용해 주세요.
            가맹사업 관련 서면 제공 시에는 가맹사업법에서 정한 양식과 기준에 따라 작성하셔야 합니다.
            인증키를 등록하기 전에는 플랫폼 동작 확인을 위한 데모 통계가 표시됩니다.
        </p>
    </div>
</section>
@endsection
