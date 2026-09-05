# MarketScope — 상권분석 플랫폼

공공데이터포털의 **지역 유동인구 · 카드매출** 공개데이터를 수집·저장하고,
회원이 지역을 고르면 행정동 단위 **상권분석 리포트**(웹 + PDF)를 만들어 주는 Laravel 12 애플리케이션입니다.
UI/UX는 [loplat.com](https://www.loplat.com/) 의 구성과 톤을 따릅니다.

---

## 1. 요구 사항

| 항목 | 버전 |
|---|---|
| PHP | 8.2 이상 (개발 환경 8.5.3) |
| Composer | 2.x |
| MySQL / MariaDB | 10.4 이상 |
| Node.js | 20 이상 |

> 반경 분석 쿼리가 `PI()`, `ACOS()`, `RADIANS()` 같은 MySQL 수학 함수를 사용하므로
> **SQLite 는 지원하지 않습니다.** 테스트도 MariaDB(`market_test`)를 사용합니다.

---

## 2. 설치

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

`.env` 에서 DB 접속 정보를 채웁니다.

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=market
DB_USERNAME=root
DB_PASSWORD=
```

데이터베이스를 만들고 마이그레이션 · 시드를 실행합니다.

```bash
mysql -u root -e "CREATE DATABASE market DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate
php artisan db:seed
npm run build
```

시드가 하는 일:

| 시더 | 내용 |
|---|---|
| `RegionSeeder` | 서울 행정동 424곳 (코드 · 중심좌표 · 면적 · 경계 폴리곤) |
| `IndustrySeeder` | 카드매출 업종 마스터 14종 |
| `DataSourceSeeder` | 리포트 "데이터 출처" 표의 기본 행 |
| `DemoStatisticsSeeder` | 인증키 없이도 동작을 확인할 수 있는 **데모 통계** |

데모 계정: `demo@marketscope.test` / `demo1234` (관리자 권한)

> **수록 범위**: 기본 시드에 들어 있는 행정동은 **서울특별시 424곳**입니다.
> 전국으로 넓히려면 전국 행정동 중심점 CSV 를 `storage/app/seed/dong_center.csv` 형식
> (`코드,시도명,시군구명,읍면동명,X(경도),Y(위도)`)으로 바꿔 넣고
> `php artisan opendata:import regions <파일>` 또는 `php artisan db:seed --class=RegionSeeder` 를 실행하세요.
> 경계 GeoJSON(`dong_boundary.geojson`, `properties.adm_nm` 에 전체 명칭)을 함께 두면
> 면적과 반경 겹침이 실제 경계로 계산됩니다.

### 실행 방법 1 — XAMPP (Apache) 서브폴더

`htdocs/market` 에 두면 **`php artisan serve` 없이** `http://localhost/market` 으로 바로 열립니다.

프로젝트 루트의 `index.php` 와 `.htaccess` 가 이를 처리합니다.

| 파일 | 역할 |
|---|---|
| `index.php` | 루트에서 Laravel 을 부트스트랩해 base 경로를 `/market` 으로 인식시킴 |
| `.htaccess` | `public/` 의 정적 파일은 그대로 서빙, 나머지는 루트 `index.php` 로 전달<br>소스·설정·로그·의존성 디렉터리와 점 파일(`.env`, `.git` …)은 403 차단 |

폴더 이름을 `market` 이 아닌 것으로 바꾸면 `.htaccess` 의 `RewriteBase /market/`,
`%{DOCUMENT_ROOT}/market/public/$1` 두 줄과 `.env` 의 `APP_URL` 을 함께 고쳐야 합니다.

> **PHP 버전 주의**
> XAMPP 의 Apache 는 자체 PHP(예: 8.2.12)를 쓰고 CLI 는 다른 버전일 수 있습니다.
> 더 높은 CLI 로 `composer install` 하면 vendor 가 그 버전을 요구하도록 빌드되어
> Apache 에서 `platform_check` 오류가 납니다.
> 이를 막기 위해 `composer.json` 에 `config.platform.php` 를 **Apache 의 PHP 버전**으로
> 고정해 두었습니다. 환경이 다르면 그 값을 바꾸고 `composer update -W` 를 실행하세요.

> **운영 배포**
> 루트 진입점 방식은 프로젝트 루트가 문서 루트 안에 놓이는 구조입니다.
> 운영 서버에서는 가상호스트의 `DocumentRoot` 를 `public/` 으로 지정하고
> 루트 `index.php` · `.htaccess` 는 쓰지 않는 편이 안전합니다.

### 실행 방법 2 — 내장 서버

```bash
php artisan serve      # http://127.0.0.1:8000
npm run dev            # 프런트엔드 개발 서버(HMR)
```

이 경우 `.env` 의 `APP_URL` 을 `http://127.0.0.1:8000` 으로 바꿔 주세요.

---

## 3. 공공데이터 수집

### 3-1. 오픈 API

1. [data.go.kr](https://www.data.go.kr) 에서 활용신청 후 인증키(**Decoding**)를 발급받습니다.
2. `.env` 에 넣고 설정 캐시를 지웁니다.

```dotenv
OPENDATA_SERVICE_KEY=발급받은_디코딩_키
```

```bash
php artisan config:clear
php artisan opendata:sync floating_population --ym=202608
php artisan opendata:sync card_sales --ym=202608
```

기관마다 엔드포인트와 응답 필드가 다르므로, 실제 신청한 API 에 맞춰
`config/opendata.php` 의 `datasets.*` 정의(`url` · `items_path` · `map`)만 고치면 됩니다.

### 3-2. CSV 파일데이터

포털의 "파일데이터"(대개 CP949 인코딩)는 인증키 없이 바로 적재할 수 있습니다.

```bash
php artisan opendata:import card_sales storage/app/seed/sales.csv --ym=202608
```

지원하는 종류: `regions`, `resident_population`, `households`, `workplace_population`,
`floating_population`, `card_sales`, `card_sales_demographics`, `students`, `academies`, `apartment_move_ins`

한글 헤더(`행정동코드`, `유동인구수`, `주거유형` …)와 한글 코드값(`남`/`여`, `평일`/`주말`,
`아파트`/`오피스텔`, `어린이집` …)은 자동으로 내부 표준값으로 변환됩니다.
같은 `행정동 × 기준월 × 교차축` 조합은 유일 키라서 다시 넣으면 **갱신**됩니다.

적재 현황과 수집 이력은 관리자 계정으로 `/admin/data` 에서 확인합니다.

---

## 4. 분석이 계산되는 방식

### 반경 분석

원이 행정동 경계를 가로지르면 그 동의 통계를 그대로 더할 수 없습니다.
`RegionResolver` 는 원 안에 격자점(61×61)을 뿌려 각 점이 속한 행정동을 세고,

```
가중치 = (그 동에 떨어진 점 수 / 원 안 전체 점 수) × 원 면적 ÷ 행정동 면적
```

로 **겹친 면적 비율**을 구합니다. 모든 통계는 이 가중치만큼 안분해 합산합니다.
경계 폴리곤이 없는 행정동은 "면적이 같은 원"으로 보는 원–원 교집합 근사로 대체합니다.

### 행정동 분석

선택한 동의 통계를 가중치 1.0 으로, 즉 그대로 합산합니다.

### 비교 기준선

`BenchmarkService` 가 같은 시도·시군구에 속한 **행정동 1곳당 평균**을 계산해
"서울특별시 평균", "강서구 평균" 열로 함께 보여 줍니다.

---

## 5. 리포트 구성

웹 화면(`/analyses/{uuid}`)과 PDF(`/analyses/{uuid}/report.pdf`)가 같은 payload 를 렌더링합니다.

1. 분석 범위 (포함 행정동과 겹침 비율)
2. 인구 요약 — 거주인구 · 배후세대 · 점심/저녁 유동인구 · 직장인구 + 상위 지역 평균 비교
3. 인구 상세 — 거주인구(성·연령), 배후세대(주거유형), 입주예정 아파트, 직장인구, 유동인구(요일·시간대)
4. 카드매출 — 총액 · 건수 · 건당 단가, 업종별, 요일·시간대별, 성·연령별
5. 학생 수 / 학원 수
6. 데이터 출처

"분석 결과" 문단은 `InsightWriter` 가 집계값에서 자동으로 씁니다
(조사 처리는 `App\Support\Korean`).

PDF 는 dompdf 로 만들며 한글 글꼴은 `storage/fonts/NanumGothic-*.ttf` 를 임베드합니다.
차트는 dompdf 가 canvas 를 그리지 못하므로 표 기반 가로 막대로 렌더링합니다.

---

## 6. 지도

`.env` 에 카카오 JavaScript 키를 넣으면 지역 선택 화면에서 지도를 클릭해 중심을 지정할 수 있습니다.

```dotenv
KAKAO_MAP_JS_KEY=자바스크립트_키
```

키가 없으면 지도 대신 검색 · 좌표 직접 입력 패널이 표시되며, 분석 기능 자체는 그대로 동작합니다.

---

## 7. 테스트

```bash
mysql -u root -e "CREATE DATABASE market_test DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan test
```

---

## 8. 주요 디렉터리

```
app/
  Console/Commands/       opendata:sync · opendata:import
  Http/Controllers/       공개 · 인증 · 분석 · 리포트 · 관리자
  Models/                 행정동 · 경계 · 통계 · 분석
  Services/
    Analysis/             RegionResolver · StatisticsRepository · BenchmarkService
                          MarketAnalyzer · InsightWriter · AnalysisRunner
    OpenData/             PublicDataClient · RecordNormalizer · DatasetWriter
                          DatasetSynchronizer · CsvImporter
  Support/                Taxonomy(코드·라벨 사전) · Korean(조사 처리)
config/
  opendata.php            엔드포인트 · 필드 매핑 · 코드 정규화 사전
  map.php                 지도 키 · 반경 기본값
resources/views/
  layouts/                공개 사이트 · 앱 셸
  analyses/               지역 선택 · 리포트
  reports/pdf.blade.php   PDF 리포트
storage/app/seed/         행정동 중심점 CSV · 경계 GeoJSON
storage/fonts/            PDF 용 나눔고딕
```

### 함께 담긴 공개 자료

| 파일 | 출처 | 라이선스 |
|---|---|---|
| `storage/app/seed/dong_center.csv`<br>`storage/app/seed/dong_boundary.geojson` | [cubensys/Korea_District](https://github.com/cubensys/Korea_District) — 서울시 행정동 중심점 · 경계 (2017) | 저장소 표기 참조 |
| `storage/fonts/NanumGothic-*.ttf` | [Google Fonts — Nanum Gothic](https://fonts.google.com/specimen/Nanum+Gothic) | SIL Open Font License 1.1 |

---

## 9. 데이터 이용 안내

모든 통계는 집계 방법과 기준일, 분석 방법에 따라 오차가 발생할 수 있으므로 참고용으로만 활용해 주세요.
가맹사업 관련 **서면 제공 시에는 가맹사업법이 정한 양식과 기준**에 따라 작성해야 합니다.
인증키를 등록하고 실제 데이터를 적재하기 전까지 리포트에 표시되는 수치는 `DemoStatisticsSeeder` 가
만든 데모 값입니다.
