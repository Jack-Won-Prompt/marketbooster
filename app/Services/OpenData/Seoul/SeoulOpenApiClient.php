<?php

namespace App\Services\OpenData\Seoul;

use App\Support\Period;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 서울 열린데이터광장 오픈 API 호출기.
 *
 * data.go.kr 과 규약이 다르다.
 *   - 인증키가 쿼리스트링이 아니라 경로에 들어간다
 *   - 페이징이 pageNo/numOfRows 가 아니라 START_INDEX/END_INDEX 이고 한 번에 1,000건까지다
 *   - 응답 루트가 서비스명 아래에 있고, 결과 코드는 RESULT.CODE 로 온다
 */
class SeoulOpenApiClient
{
    /** 정상 처리 코드 */
    private const OK = 'INFO-000';

    /** 데이터가 더 없을 때 (마지막 페이지 다음) */
    private const NO_DATA = 'INFO-200';

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $baseUrl = 'http://openapi.seoul.go.kr:8088',
        private readonly int $timeout = 30,
        private readonly int $pageSize = 1000,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('seoul.api_key'),
            rtrim((string) config('seoul.base_url'), '/'),
            (int) config('seoul.timeout', 30),
            (int) config('seoul.page_size', 1000),
        );
    }

    public function hasKey(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * 한 페이지를 가져온다.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function fetchPage(string $service, Period $period, int $start, int $end): array
    {
        if (! $this->hasKey()) {
            throw new RuntimeException('SEOUL_OPENAPI_KEY 가 설정되지 않았습니다. 서울 열린데이터광장에서 일반 인증키를 발급받아 .env 에 넣어주세요.');
        }

        $url = sprintf(
            '%s/%s/json/%s/%d/%d/%s',
            $this->baseUrl,
            rawurlencode($this->apiKey),
            $service,
            $start,
            $end,
            $period->isQuarter() ? $period->code : ''
        );

        $response = Http::timeout($this->timeout)
            // 서울 API 는 Content-Encoding 헤더에 압축방식이 아니라 'UTF-8' 을 실어 보낸다.
            // curl 이 이걸 압축으로 해석하려다 실패하므로(cURL error 61) 자동 해제를 끈다.
            ->withOptions(['decode_content' => false])
            ->retry(3, 400, throw: false)
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException("서울 열린데이터광장 호출 실패 (HTTP {$response->status()})");
        }

        $json = $response->json() ?? [];

        // 인증 실패·쿼터 초과 등은 서비스 키 없이 최상위 RESULT 로 온다.
        $topCode = Arr::get($json, 'RESULT.CODE');

        if ($topCode !== null && $topCode !== self::OK) {
            if ($topCode === self::NO_DATA) {
                return ['rows' => [], 'total' => 0];
            }

            throw new RuntimeException(
                "서울 열린데이터광장 오류 [{$topCode}] ".Arr::get($json, 'RESULT.MESSAGE', '알 수 없는 오류')
            );
        }

        $code = Arr::get($json, "{$service}.RESULT.CODE");

        if ($code !== null && ! in_array($code, [self::OK, self::NO_DATA], true)) {
            throw new RuntimeException(
                "서울 열린데이터광장 오류 [{$code}] ".Arr::get($json, "{$service}.RESULT.MESSAGE", '알 수 없는 오류')
            );
        }

        $rows = Arr::get($json, "{$service}.row", []);

        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => (int) Arr::get($json, "{$service}.list_total_count", 0),
        ];
    }

    /**
     * 마지막 페이지까지 순회하며 배치 단위로 콜백을 호출한다.
     *
     * @param  callable(array<int, array<string, mixed>>, int): void  $onBatch
     * @return int 총 수신 건수
     */
    public function each(string $service, Period $period, callable $onBatch, ?int $maxPages = null): int
    {
        $start = 1;
        $page = 1;
        $received = 0;

        while (true) {
            $end = $start + $this->pageSize - 1;
            $result = $this->fetchPage($service, $period, $start, $end);

            if ($result['rows'] === []) {
                break;
            }

            $onBatch($result['rows'], $page);
            $received += count($result['rows']);

            if (count($result['rows']) < $this->pageSize) {
                break;
            }

            if ($result['total'] > 0 && $end >= $result['total']) {
                break;
            }

            if ($maxPages !== null && $page >= $maxPages) {
                break;
            }

            $start = $end + 1;
            $page++;
        }

        return $received;
    }
}
