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
> 다른 시도를 넣으려면 `php artisan regions:import 경기도 --download` 를 실행하세요 (3-0 참고).
> 인증키가 필요 없고, 시도명을 생략하면 전국 3,558곳이 들어갑니다.

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

### 3-0. 행정동 경계 — 모든 분석의 기준

모든 통계는 행정동코드(`regions.code`, 행정안전부 8자리)로 연결됩니다.
분석하려는 지역의 행정동과 경계 폴리곤이 먼저 들어 있어야 합니다.

```bash
php artisan regions:import 경기도 --download   # 원본 GeoJSON(약 34MB)을 받아 경기도만 적재
php artisan regions:import 서울특별시 경기도    # 이미 받아 뒀다면 시도명만
php artisan regions:import                     # 시도명을 생략하면 전국 3,558개 행정동
```

- 출처: [vuski/admdongkor](https://github.com/vuski/admdongkor) — 행정안전부 행정동 경계, 분기마다 갱신
- `adm_cd2`(10자리)의 **앞 8자리**가 행정안전부 행정동코드이고, 이것이 통계 테이블의 `region_code` 입니다.
- 면적 · 중심점 · bounding box 는 폴리곤에서 직접 계산합니다. 인증키가 필요 없습니다.
- 34MB 파일을 통째로 `json_decode` 하면 메모리가 터지므로 피처 한 줄씩 흘려 읽습니다.

### 3-0-1. 지금 어디까지 수록돼 있나

행정동은 전국을 넣을 수 있지만 **통계 출처는 시도마다 다릅니다.**
`/admin/data` 의 "시도별 수록 범위" 표가 실제 기준이며, 현재 상황은 이렇습니다.

| 시도 | 행정동 | 거주인구 · 배후세대 · 직장인구 · 유동인구 · 카드매출 | 점포 · 프랜차이즈 |
|---|---|---|---|
| 서울특별시 | 424 | ● 서울시 상권분석서비스 (3-1) | ● 소상공인 상가정보 (3-2) |
| 경기도 | 602 | – 행정동 단위 공개 출처 없음 | ● 소상공인 상가정보 (3-2) |

경기도에서 유동인구·카드매출을 채우지 못하는 이유는 확인해 본 결과 이렇습니다.

- `경기도_가맹사업_유동인구정보` — **시군구 · 연 단위** (행정동 아님)
- `경기도_가맹사업_매출정보` — **시도 · 연 단위**
- `경기도 발달골목상권 추정매출 현황` — **상권ID 단위**, 행정동으로 환산 불가
- `행정안전부_행정동별(통반단위) 주민등록 인구` (data.go.kr [15108072](https://www.data.go.kr/data/15108072/openapi.do) ·
  [15108065](https://www.data.go.kr/data/15108065/openapi.do)) — 전국 행정동 단위로 **거주인구·세대수는 채울 수 있습니다.**
  다만 데이터셋별 활용신청(자동승인)이 필요하고, 아직 신청 전이라 미수록입니다.

리포트는 미수록 항목을 **0 으로 그리지 않고 "미수록" 으로 표시**하고,
해당 항목에 대한 분석 문장과 평균 대비 등급도 만들지 않습니다.
수록된 통계가 하나도 없는 지역은 분석 자체를 거부합니다.

### 3-1. 서울시 상권분석서비스 — 가장 빠른 길 (권장)

전국 단위 유동인구·카드매출 오픈 API 는 data.go.kr 에 없습니다.
반면 **서울 열린데이터광장**에는 행정동 단위로 필요한 지표가 모두 있고,
행정동 코드가 행정안전부 주민등록 행정기관코드라 `regions.code` 와 그대로 맞습니다.

**API 별 활용신청이 없습니다.** [일반 인증키](https://data.seoul.go.kr/together/mypage/actkeyMain.do)
하나만 발급받으면 아래 서비스를 모두 호출할 수 있습니다.

| 내부 데이터 | 서비스명 | 데이터셋 |
|---|---|---|
| 유동인구 | `VwsmAdstrdFlpopW` | [OA-22178 길단위인구-행정동](https://data.seoul.go.kr/dataList/OA-22178/S/1/datasetView.do) |
| 카드매출 | `VwsmAdstrdSelngW` | [OA-22175 추정매출-행정동](https://data.seoul.go.kr/dataList/OA-22175/S/1/datasetView.do) |
| 거주인구 · 배후세대 | `VwsmAdstrdRepopW` | [OA-22183 상주인구-행정동](https://data.seoul.go.kr/dataList/OA-22183/S/1/datasetView.do) |
| 직장인구 | `VwsmAdstrdWrcPopltnW` | [OA-22184 직장인구-행정동](https://data.seoul.go.kr/dataList/OA-22184/S/1/datasetView.do) |

발급받은 키는 `.env` 를 직접 고치지 말고 커맨드로 넣으면 형식이 깨질 일이 없습니다.

```bash
php artisan opendata:key seoul     # 붙여넣기 (화면에 표시되지 않음)
php artisan opendata:key datago
php artisan opendata:key --show    # 현재 설정 상태를 가려서 확인
```

```bash
php artisan config:clear
php artisan opendata:check                # 키가 실제로 통하는지 먼저 확인
php artisan seoul:sync all --yq=20242     # 2024년 2분기
php artisan seoul:sync card_sales --yq=20243
```

### 3-2. 소상공인 상가(상권)정보 — 전국 점포·업종

전국 점포 목록(상호·업종·좌표)은 data.go.kr 에서 **활용신청**해야 합니다 (자동승인).

- [소상공인시장진흥공단_상가(상권)정보_API (15012005)](https://www.data.go.kr/data/15012005/openapi.do)
- Base URL: `https://apis.data.go.kr/B553077/api/open/sdsc2`

```bash
php artisan opendata:key datago    # Decoding 키를 붙여넣기
php artisan sbiz:sync-stores --sido=경기도                    # 시군구별 프로세스로 나눠 수집
php artisan sbiz:sync-stores --sido=경기도 --skip-collected   # 중단된 지점부터 이어서
php artisan sbiz:sync-stores --sido=서울특별시 --sigungu=강서구
php artisan sbiz:sync-stores --dong=11500603
```

`--sigungu` 없이 시도 전체를 부르면 시군구마다 **자식 프로세스**를 새로 띄웁니다.
한 프로세스로 600개 행정동을 돌면 메모리가 계속 늘어 중간에 죽고,
한 시군구가 실패해도 나머지가 멈추기 때문입니다.
`--skip-collected` 는 이미 점포가 들어 있는 행정동을 건너뛰어 이어받기에 씁니다.

### 3-2-1. 분야 · 프랜차이즈 분류

점포를 모아 두는 것만으로는 "디저트 상권인가, 식당 상권인가" 를 알 수 없습니다.
수집한 점포에 **분야**와 **프랜차이즈 브랜드**를 붙이는 단계가 따로 있습니다.

```bash
php artisan stores:classify            # 아직 분류되지 않은 점포만
php artisan stores:classify --reset    # 처음부터 다시
```

**분야** (`App\Support\StoreSectors`) 는 표준 업종코드를 창업 관점으로 다시 묶은 것입니다.
소분류 → 중분류 → 대분류 순으로 판별해 좁은 코드가 항상 이깁니다.

> 표준 분류에서는 빵집·아이스크림이 치킨집과 같은 "기타 간이" 에 들어 있습니다.
> 그대로 쓰면 디저트 상권이 보이지 않으므로 소분류로 갈라
> `카페·디저트` 와 `패스트푸드·분식` 으로 나눕니다.

식당 · 카페·디저트 · 패스트푸드·분식 · 주점 · 편의점·마트 · 식품 소매 ·
패션·잡화 · 뷰티·미용 · 의료·건강 · 교육·학원 · 스포츠·여가 · 숙박 ·
생활 서비스 · 전문 서비스 · 기타 소매

**프랜차이즈 브랜드** 는 두 단계로 찾습니다.

1. **사전 매칭** — `App\Support\Franchises::BRANDS` 에 등록된 표기를 상호에서 찾습니다.
   상가정보의 상호에는 지점이 붙어 오기 때문에(`지에스25마곡`, `이디야마곡`)
   정확히 일치시키는 방식으로는 같은 브랜드가 수백 개로 쪼개집니다.
   표기가 갈리는 것도 한 브랜드로 모읍니다. (`파리바게뜨`/`파리바게트`, `비비큐`/`BBQ`)
2. **데이터 매칭** — 사전에 없더라도 같은 상호가 **행정동 3곳 이상**에 반복되면 체인으로 봅니다.
   지역 체인까지 사전에 담을 수는 없기 때문이고, 한 동네에 같은 상호가 몇 개 있다고 해서
   프랜차이즈는 아니므로 기준을 "여러 행정동" 으로 둡니다.

결과는 `stores.sector` · `stores.brand` · `stores.is_franchise` 에 저장됩니다.
`sbiz:sync-stores` 로 새로 수집한 점포는 저장하는 순간 사전 매칭까지 끝나 있고,
데이터 매칭(2번)은 전체를 봐야 하므로 `stores:classify` 를 한 번 더 돌려야 반영됩니다.

### 3-3. 그 밖의 공공데이터포털 오픈 API

기관마다 엔드포인트와 응답 필드가 달라, 신청한 API 에 맞춰
`config/opendata.php` 의 `datasets.*` 정의(`url` · `items_path` · `map`)를 채운 뒤 실행합니다.

> `config/opendata.php` 에 기본값으로 들어 있는 두 URL 은 **형식 예시(자리표시자)** 입니다.
> 실제로 존재하는 서비스가 아니므로 신청한 API 주소로 바꿔야 동작합니다.

```bash
php artisan opendata:sync floating_population --ym=202608
```

### 3-4. CSV 파일데이터

포털의 "파일데이터"(대개 CP949 인코딩)는 인증키 없이 바로 적재할 수 있습니다.

```bash
php artisan opendata:import card_sales <파일.csv> --ym=202608   # 월
php artisan opendata:import card_sales <파일.csv> --ym=20242    # 분기
```

지원하는 종류: `regions`, `resident_population`, `households`, `workplace_population`,
`floating_population`, `card_sales`, `card_sales_demographics`, `students`, `academies`, `apartment_move_ins`

한글 헤더(`행정동코드`, `유동인구수`, `주거유형` …)와 한글 코드값(`남`/`여`, `평일`/`주말`,
`아파트`/`오피스텔`, `어린이집` …)은 자동으로 내부 표준값으로 변환됩니다.
같은 `행정동 × 기간 × 교차축` 조합은 유일 키라서 다시 넣으면 **갱신**됩니다.

적재 현황과 수집 이력은 관리자 계정으로 `/admin/data` 에서 확인합니다.

### 3-5. 기준 기간 — 월과 분기

출처마다 집계 주기가 다릅니다.

| 출처 | 주기 | 저장 칸 |
|---|---|---|
| 서울시 상권분석서비스 | 분기 (`20242`) | `base_yq` |
| 행정안전부 주민등록인구, 학원 인허가 등 | 월 (`202608`) | `base_ym` |

한쪽으로 억지로 변환하면 원본이 훼손되므로 두 칸을 함께 두고, 쓰지 않는 쪽은 빈 문자열로 남깁니다.
어느 칸으로 걸러야 하는지는 [`App\Support\Period`](app/Support/Period.php) 가 한 곳에서 결정합니다.
분석 화면의 **기준 기간** 드롭다운에는 실제로 적재된 기간만 나옵니다.

### 3-6. 서울 데이터에서 추정이 섞이는 지점

서울 API 는 교차표가 아니라 **주변분포(marginal)** 만 줍니다.
예를 들어 유동인구는 시간대별·요일별·성별·연령대별 합계를 각각 줄 뿐,
"평일 점심 30대 여성" 같은 칸은 주지 않습니다.

우리 스키마는 교차표라서 각 축의 비율을 곱해 칸을 채웁니다.

```
칸 = 시간대합계 × 요일비율 × 성별비율 × 연령비율
```

이렇게 채우면 **어느 축으로 합산하든 원본 주변분포가 그대로 복원**됩니다.
리포트가 보여 주는 값은 모두 주변합이므로 정확하고, 개별 교차 칸만 독립 가정에 따른 추정치입니다.
([`SeoulTransformerTest`](tests/Unit/SeoulTransformerTest.php) 가 이 성질을 검증합니다.)

그 밖에 알아 두실 점:

- 상주인구·직장인구는 성 × 연령 교차값(`MAG_`/`FAG_`)을 원본이 직접 주므로 **추정이 없습니다.**
- 서울은 **10대 미만과 70대 이상을 제공하지 않습니다.** 해당 구간은 리포트에서 비어 있습니다.
- 새벽(00~06) 구간은 버리지 않고 **밤(21:00~05:59)** 에 합산합니다.
- 배후세대는 **아파트 / 비아파트** 두 가지로만 제공됩니다.

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

## 5. 위치 상권 현황 (지도)

`/map` 에서 지도를 클릭하면 그 지점 반경의 상권을 즉석에서 계산해 오른쪽 패널에 보여 줍니다.
저장 없이 미리 보고, 마음에 들면 그 자리에서 리포트로 만들어 PDF 로 내려받습니다.

- 지도는 **Leaflet + OpenStreetMap** 이라 API 키가 필요 없습니다.
- 반경 150m ~ 3km, 행정동 검색으로 이동, 기준 기간 전환
- 검색창 옆 **검색 버튼**(또는 Enter)은 목록을 고르지 않아도 가장 잘 맞는 곳으로 바로 이동합니다.
- 패널: 포함 행정동과 겹침 비율 · 핵심 지표 · 시간대별 유동인구(평일/주말) ·
  카드매출과 업종 Top · **업종 분야와 프랜차이즈 브랜드** · 분석 문단
- 미수록 항목은 패널에서 감춰지고, 무엇이 빠졌는지 한 줄로 알려 줍니다.
- 미리보기는 `GET /api/regions/market` 이 담당하며 analyses 테이블에 남지 않습니다.

### 새 상권분석 (`/analyses/new`)

- **2. 지역 선택** — 행정동을 입력하고 **조회**(또는 Enter)를 누르면 그 위치로 지도가 이동하고
  반경 안에 걸리는 행정동을 바로 미리 보여 줍니다. 후보가 여럿이면 목록에 남습니다.
- **3. 리포트 정보** — 분석 이름은 선택한 지역·반경에 맞춰 자동으로 채워집니다.
  (`마곡나루역 반경 1,000m`) 직접 고치면 그때부터 자동 생성을 멈춥니다.

---

## 6. 리포트 구성

웹 화면(`/analyses/{uuid}`)과 PDF(`/analyses/{uuid}/report.pdf`)가 같은 payload 를 렌더링합니다.

1. 분석 범위 (포함 행정동과 겹침 비율)
2. 인구 요약 — 거주인구 · 배후세대 · 점심/저녁 유동인구 · 직장인구 + 상위 지역 평균 비교
3. 인구 상세 — 거주인구(성·연령), 배후세대(주거유형), 입주예정 아파트, 직장인구, 유동인구(요일·시간대)
4. 카드매출 — 총액 · 건수 · 건당 단가, 업종별, 요일·시간대별, 성·연령별
5. 업종 분야 · 프랜차이즈 — 분야별 점포, 분야별 프랜차이즈 비중, 브랜드 목록, 세부 업종
6. 학생 수 / 학원 수
7. 데이터 출처

프랜차이즈 브랜드 목록은 화면에서 **CSV 로 내려받을 수 있습니다**
(`/analyses/{uuid}/franchises.csv`). 분야 · 브랜드명 · 매장 수 · 비중이 들어갑니다.
엑셀에서 한글이 깨지지 않도록 UTF-8 BOM 을 붙여 내보냅니다.

**미수록 항목 처리.** payload 의 `meta.coverage` 에 항목별 수록 여부가 들어 있습니다
(`StatisticsRepository::coverage()`). 미수록 항목은

- 요약 카드에 `—` 와 "미수록" 배지를 표시하고, 비교 그래프·표에서 아예 빼며
- 평균 대비 등급(`levels`)을 `null` 로 두고
- `InsightWriter` 가 그 항목의 분석 문장을 쓰지 않고
- PDF 는 해당 장과 목차 항목을 통째로 생략합니다.

0 을 사실처럼 그리지 않기 위한 장치입니다. 수록된 통계가 하나도 없으면 분석을 거부합니다.

"분석 결과" 문단은 `InsightWriter` 가 집계값에서 자동으로 씁니다
(조사 처리는 `App\Support\Korean`).

PDF 는 dompdf 로 만들며 웹과 같은 Pretendard(`storage/fonts/Pretendard-*.ttf`)를 임베드합니다.
차트는 dompdf 가 canvas 를 그리지 못하므로 표 기반 가로 막대로 렌더링합니다.

---

## 7. 지도

**지도 API 키가 필요 없습니다.** 두 화면 모두 Leaflet + OpenStreetMap 을 씁니다.

| 화면 | 지도 |
|---|---|
| 위치 상권 현황 `/map` | 전체 화면 지도, 클릭한 지점의 상권을 즉시 계산 |
| 새 상권분석 `/analyses/new` | 중심 지점 지정용 지도 |

다른 타일 서버를 쓰려면 `.env` 에서 바꿉니다.

```dotenv
MAP_TILE_URL="https://{s}.tile.example.com/{z}/{x}/{y}.png"
MAP_TILE_ATTRIBUTION="&copy; Example"
```

> OpenStreetMap 타일은 [이용 정책](https://operations.osmfoundation.org/policies/tiles/)이 있습니다.
> 트래픽이 늘면 자체 타일 서버나 상용 타일로 바꾸는 편이 좋습니다.

---

## 8. 테스트

```bash
mysql -u root -e "CREATE DATABASE market_test DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan test
```

---

## 9. 주요 디렉터리

```
app/
  Console/Commands/       regions:import · opendata:sync · opendata:import
                          seoul:sync · sbiz:sync-stores · stores:classify
                          opendata:key · opendata:check
  Http/Controllers/       공개 · 인증 · 분석 · 리포트 · 관리자
  Models/                 행정동 · 경계 · 통계 · 분석
  Services/
    Analysis/             RegionResolver · StatisticsRepository · BenchmarkService
                          MarketAnalyzer · InsightWriter · AnalysisRunner
    OpenData/             PublicDataClient · RecordNormalizer · DatasetWriter
                          DatasetSynchronizer · CsvImporter
    OpenData/Seoul/       SeoulOpenApiClient · SeoulSynchronizer · Transformers(가로→세로)
    OpenData/Sbiz/        StoreCollector (상가·상권정보)
    Regions/              HangJeongDongImporter (전국 행정동 경계 GeoJSON 적재)
    Stores/               StoreClassifier (분야 · 프랜차이즈 분류)
  Support/                Taxonomy(코드·라벨 사전) · Period(월/분기) · Korean(조사 처리)
                          StoreSectors(분야 사전) · Franchises(브랜드 사전)
config/
  opendata.php            엔드포인트 · 필드 매핑 · 코드 정규화 사전
  seoul.php               서울 열린데이터광장 서비스 정의
  sbiz.php                소상공인 상가정보 API 정의
  map.php                 지도 키 · 반경 기본값
resources/views/
  layouts/                공개 사이트 · 앱 셸
  analyses/               지역 선택 · 리포트
  reports/pdf.blade.php   PDF 리포트
storage/app/seed/         행정동 중심점 CSV · 경계 GeoJSON(서울/전국) · 서울 API 필드 명세
storage/fonts/            PDF 용 Pretendard (웹과 동일)
public/images/            랜딩용 SVG 삽화 (파일 안에서 자체 애니메이션)
```

### 랜딩 페이지의 이미지와 모션

삽화는 모두 브랜드 팔레트로 직접 그린 SVG이고, 파일 안에 CSS 애니메이션이 들어 있어
`<img>` 로 불러도 움직입니다. 외부 이미지 호스트에 의존하지 않습니다.

스크롤 인터랙션은 `resources/js/motion.js` 가 담당합니다.

| 속성 | 동작 |
|---|---|
| `data-reveal` | 화면에 들어오면 나타남 (`left` · `right` · `zoom`) |
| `data-reveal-stagger="80"` | 자식들을 순서대로 등장 |
| `data-count-to="45499"` | 0에서 그 값까지 세어 올림 |
| `data-parallax="0.06"` | 스크롤에 따라 살짝 어긋나게 이동 |

`prefers-reduced-motion` 을 켠 사용자에게는 모든 움직임이 멈추고 최종 상태만 보입니다.
숨김 상태는 `<html class="js-reveal">` 이 있을 때만 적용되므로, 스크립트가 막혀도
본문이 사라지지 않습니다.

### 함께 담긴 공개 자료

| 파일 | 출처 | 라이선스 |
|---|---|---|
| `storage/app/seed/dong_center.csv`<br>`storage/app/seed/dong_boundary.geojson` | [cubensys/Korea_District](https://github.com/cubensys/Korea_District) — 서울시 행정동 중심점 · 경계 (2017) | 저장소 표기 참조 |
| `storage/fonts/Pretendard-*.ttf` | [Pretendard](https://github.com/orioncactus/pretendard) | SIL Open Font License 1.1 |

---

## 10. 데이터 이용 안내

모든 통계는 집계 방법과 기준일, 분석 방법에 따라 오차가 발생할 수 있으므로 참고용으로만 활용해 주세요.
가맹사업 관련 **서면 제공 시에는 가맹사업법이 정한 양식과 기준**에 따라 작성해야 합니다.
인증키를 등록하고 실제 데이터를 적재하기 전까지 리포트에 표시되는 수치는 `DemoStatisticsSeeder` 가
만든 데모 값입니다.
