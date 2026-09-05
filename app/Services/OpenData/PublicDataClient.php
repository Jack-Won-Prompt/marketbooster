<?php

namespace App\Services\OpenData;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 공공데이터포털(data.go.kr) 오픈 API 호출기.
 *
 * 포털 API 는 기관별로 응답 포맷(JSON/XML)과 페이지 파라미터 이름이 조금씩 다르지만
 * 공통적으로 serviceKey / pageNo / numOfRows 를 사용한다. 이 클래스는 그 공통부만 담당하고
 * 응답 해석은 config/opendata.php 의 items_path · map 정의에 맡긴다.
 */
class PublicDataClient
{
    public function __construct(
        private readonly ?string $serviceKey = null,
        private readonly int $timeout = 30,
        private readonly bool $verifySsl = false,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('opendata.service_key'),
            (int) config('opendata.timeout', 30),
            (bool) config('opendata.verify_ssl', false),
        );
    }

    public function hasKey(): bool
    {
        return filled($this->serviceKey);
    }

    /**
     * 한 페이지를 가져와 items 배열을 돌려준다.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchPage(string $url, array $params, string $itemsPath, int $page, int $perPage): array
    {
        if (! $this->hasKey()) {
            throw new RuntimeException('OPENDATA_SERVICE_KEY 가 설정되지 않았습니다. .env 에 공공데이터포털 인증키를 넣어주세요.');
        }

        $retry = config('opendata.retry');

        $response = Http::timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl])
            ->retry($retry['times'] ?? 3, $retry['sleep_ms'] ?? 400, throw: false)
            ->get($url, array_merge($params, [
                'serviceKey' => $this->serviceKey,
                'pageNo' => $page,
                'numOfRows' => $perPage,
            ]));

        if ($response->failed()) {
            throw new RuntimeException("공공데이터 API 호출 실패 (HTTP {$response->status()}) : ".mb_substr($response->body(), 0, 300));
        }

        $decoded = $this->decode($response->body());

        $this->assertNoApiError($decoded);

        $items = Arr::get($decoded, $itemsPath, []);

        // 항목이 하나면 배열이 아니라 객체로 내려오는 기관이 있다.
        if ($items !== [] && ! array_is_list($items)) {
            $items = [$items];
        }

        return is_array($items) ? $items : [];
    }

    /**
     * 마지막 페이지까지 순회하며 배치 단위로 콜백을 호출한다.
     *
     * @param  callable(array<int, array<string, mixed>>, int): void  $onBatch
     * @return int 총 수신 건수
     */
    public function each(string $url, array $params, string $itemsPath, callable $onBatch, ?int $maxPages = null): int
    {
        $perPage = (int) config('opendata.page_size', 1000);
        $page = 1;
        $total = 0;

        while (true) {
            $items = $this->fetchPage($url, $params, $itemsPath, $page, $perPage);

            if ($items === []) {
                break;
            }

            $onBatch($items, $page);
            $total += count($items);

            if (count($items) < $perPage) {
                break;
            }

            if ($maxPages !== null && $page >= $maxPages) {
                break;
            }

            $page++;
        }

        return $total;
    }

    /** JSON 이든 XML 이든 배열로 변환한다. */
    private function decode(string $body): array
    {
        $body = trim($body);

        if ($body === '') {
            return [];
        }

        if (str_starts_with($body, '{') || str_starts_with($body, '[')) {
            return json_decode($body, true) ?? [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new RuntimeException('응답을 JSON/XML 로 해석할 수 없습니다: '.mb_substr($body, 0, 200));
        }

        return json_decode(json_encode($xml), true) ?? [];
    }

    /** 포털 공통 에러코드(resultCode !== 00)를 예외로 승격한다. */
    private function assertNoApiError(array $decoded): void
    {
        $code = Arr::get($decoded, 'response.header.resultCode')
            ?? Arr::get($decoded, 'header.resultCode')
            ?? Arr::get($decoded, 'cmmMsgHeader.returnReasonCode');

        if ($code === null || in_array((string) $code, ['00', '0', 'INFO-000'], true)) {
            return;
        }

        $message = Arr::get($decoded, 'response.header.resultMsg')
            ?? Arr::get($decoded, 'header.resultMsg')
            ?? Arr::get($decoded, 'cmmMsgHeader.errMsg')
            ?? '알 수 없는 오류';

        throw new RuntimeException("공공데이터 API 오류 [{$code}] {$message}");
    }
}
