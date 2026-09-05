<?php

namespace App\Services\OpenData\Sbiz;

use App\Models\DataImportLog;
use App\Models\Region;
use App\Services\Stores\StoreClassifier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 소상공인시장진흥공단_상가(상권)정보 수집기 (공공데이터포털 15012005).
 *
 *   Base URL : https://apis.data.go.kr/B553077/api/open/sdsc2
 *   행정동 단위 : /storeListInDong  (divId=adongCd&key=행정동코드)
 *   반경 단위   : /storeListInRadius (radius, cx, cy)
 *
 * 이 API 는 data.go.kr 활용신청이 필요하다. 승인되면 OPENDATA_SERVICE_KEY 를 그대로 쓴다.
 */
class StoreCollector
{
    public function __construct(
        private readonly ?string $serviceKey = null,
        private readonly string $baseUrl = 'https://apis.data.go.kr/B553077/api/open/sdsc2',
        private readonly int $timeout = 30,
        private readonly int $pageSize = 1000,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('sbiz.service_key') ?: config('opendata.service_key'),
            rtrim((string) config('sbiz.base_url'), '/'),
            (int) config('sbiz.timeout', 30),
            (int) config('sbiz.page_size', 1000),
        );
    }

    public function hasKey(): bool
    {
        return filled($this->serviceKey);
    }

    /**
     * 행정동 하나의 상가업소를 모두 가져와 저장한다.
     *
     * @return array{received:int, imported:int}
     */
    public function collectDong(string $regionCode, ?callable $progress = null): array
    {
        $this->assertKey();

        $page = 1;
        $received = 0;
        $imported = 0;

        while (true) {
            $items = $this->request('storeListInDong', [
                'divId' => 'adongCd',
                'key' => $regionCode,
                'pageNo' => $page,
                'numOfRows' => $this->pageSize,
                'type' => 'json',
            ]);

            if ($items === []) {
                break;
            }

            $received += count($items);
            $imported += $this->store($items);
            $progress && $progress("  {$regionCode} {$page}페이지: ".count($items).'건');

            if (count($items) < $this->pageSize) {
                break;
            }

            $page++;
        }

        return ['received' => $received, 'imported' => $imported];
    }

    /**
     * 여러 행정동을 순회한다.
     *
     * @param  array<int, string>  $regionCodes
     * @return array{received:int, imported:int, regions:int}
     */
    public function collectRegions(array $regionCodes, ?callable $progress = null): array
    {
        $log = DataImportLog::start('stores', 'api', null, $this->baseUrl.'/storeListInDong');
        $received = 0;
        $imported = 0;

        try {
            foreach ($regionCodes as $code) {
                $result = $this->collectDong($code, $progress);
                $received += $result['received'];
                $imported += $result['imported'];
            }

            $log->succeed($imported, max(0, $received - $imported));

            return ['received' => $received, 'imported' => $imported, 'regions' => count($regionCodes)];
        } catch (\Throwable $e) {
            $log->fail($e->getMessage());

            throw $e;
        }
    }

    /** 시군구에 속한 행정동 코드 목록 */
    public function regionCodesForSigungu(string $sidoName, ?string $sigunguName = null): array
    {
        return Region::where('sido_name', $sidoName)
            ->when($sigunguName, fn ($q) => $q->where('sigungu_name', $sigunguName))
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function request(string $operation, array $params): array
    {
        $response = Http::timeout($this->timeout)
            ->withOptions(['verify' => filter_var(config('opendata.verify_ssl', false), FILTER_VALIDATE_BOOL)])
            ->retry(3, 400, throw: false)
            ->get("{$this->baseUrl}/{$operation}", $params + ['serviceKey' => $this->serviceKey]);

        if ($response->failed()) {
            throw new RuntimeException("상가정보 API 호출 실패 (HTTP {$response->status()}) : ".mb_substr($response->body(), 0, 200));
        }

        $json = $response->json() ?? [];
        $code = Arr::get($json, 'header.resultCode') ?? Arr::get($json, 'response.header.resultCode');

        if ($code !== null && ! in_array((string) $code, ['00', '0'], true)) {
            $message = Arr::get($json, 'header.resultMsg') ?? Arr::get($json, 'response.header.resultMsg', '알 수 없는 오류');

            // 데이터가 더 없을 때도 에러코드로 오는 경우가 있어 빈 배열로 처리한다.
            if (str_contains((string) $message, 'NODATA')) {
                return [];
            }

            throw new RuntimeException("상가정보 API 오류 [{$code}] {$message}");
        }

        $items = Arr::get($json, 'body.items') ?? Arr::get($json, 'response.body.items.item', []);

        if (is_array($items) && $items !== [] && ! array_is_list($items)) {
            $items = [$items];
        }

        return is_array($items) ? $items : [];
    }

    private function store(array $items): int
    {
        $now = now();
        $rows = [];

        foreach ($items as $item) {
            $storeId = trim((string) ($item['bizesId'] ?? ''));
            $regionCode = preg_replace('/\D/', '', (string) ($item['adongCd'] ?? ''));

            if ($storeId === '' || $regionCode === '') {
                continue;
            }

            $classification = StoreClassifier::forRow(
                $item['bizesNm'] ?? null,
                $item['indsLclsCd'] ?? null,
                $item['indsMclsCd'] ?? null,
                $item['indsSclsCd'] ?? null,
            );

            $rows[$storeId] = $classification + [
                'store_id' => $storeId,
                'name' => mb_substr((string) ($item['bizesNm'] ?? ''), 0, 200),
                'branch_name' => mb_substr((string) ($item['brchNm'] ?? ''), 0, 120) ?: null,
                'region_code' => $regionCode,
                'sido_name' => $item['ctprvnNm'] ?? null,
                'sigungu_name' => $item['signguNm'] ?? null,
                'dong_name' => $item['adongNm'] ?? null,
                'large_code' => $item['indsLclsCd'] ?? null,
                'large_name' => $item['indsLclsNm'] ?? null,
                'middle_code' => $item['indsMclsCd'] ?? null,
                'middle_name' => $item['indsMclsNm'] ?? null,
                'small_code' => $item['indsSclsCd'] ?? null,
                'small_name' => $item['indsSclsNm'] ?? null,
                'road_address' => mb_substr((string) ($item['rdnmAdr'] ?? ''), 0, 250) ?: null,
                'lot_address' => mb_substr((string) ($item['lnoAdr'] ?? ''), 0, 250) ?: null,
                'lat' => is_numeric($item['lat'] ?? null) ? (float) $item['lat'] : null,
                'lng' => is_numeric($item['lon'] ?? null) ? (float) $item['lon'] : null,
                'collected_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            DB::table('stores')->upsert($chunk, ['store_id'], [
                'name', 'branch_name', 'region_code', 'sido_name', 'sigungu_name', 'dong_name',
                'large_code', 'large_name', 'middle_code', 'middle_name', 'small_code', 'small_name',
                'road_address', 'lot_address', 'lat', 'lng', 'collected_at', 'updated_at',
                'sector', 'brand', 'is_franchise', 'brand_source',
            ]);
        }

        return count($rows);
    }

    private function assertKey(): void
    {
        if (! $this->hasKey()) {
            throw new RuntimeException(
                '상가정보 API 인증키가 없습니다. data.go.kr 에서 "소상공인시장진흥공단_상가(상권)정보_API" 활용신청 후 '
                .'.env 의 OPENDATA_SERVICE_KEY 를 채워주세요. (https://www.data.go.kr/data/15012005/openapi.do)'
            );
        }
    }
}
