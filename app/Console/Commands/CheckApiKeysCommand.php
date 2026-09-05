<?php

namespace App\Console\Commands;

use App\Services\OpenData\Sbiz\StoreCollector;
use App\Services\OpenData\Seoul\SeoulOpenApiClient;
use App\Support\Period;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * .env 에 넣은 인증키가 실제로 통하는지 한 번에 확인한다.
 * 키를 발급받은 직후 "제대로 넣었나"를 바로 알 수 있게 한다.
 */
class CheckApiKeysCommand extends Command
{
    protected $signature = 'opendata:check {--yq= : 서울 API 확인에 쓸 분기 (기본: 직전 분기)}';

    protected $description = '설정된 공공데이터 인증키가 실제로 동작하는지 확인합니다.';

    public function handle(SeoulOpenApiClient $seoul, StoreCollector $sbiz): int
    {
        $ok = true;

        $this->newLine();
        $ok = $this->checkSeoul($seoul) && $ok;
        $this->newLine();
        $ok = $this->checkSbiz($sbiz) && $ok;
        $this->newLine();

        if ($ok) {
            $this->info('모든 인증키가 정상입니다. 이제 수집을 실행하세요.');
            $this->line('  php artisan seoul:sync all --yq='.$this->quarter());
            $this->line('  php artisan sbiz:sync-stores --sido=서울특별시 --sigungu=강서구');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function checkSeoul(SeoulOpenApiClient $client): bool
    {
        $this->line('<options=bold>서울 열린데이터광장</> (SEOUL_OPENAPI_KEY)');

        if (! $client->hasKey()) {
            $this->warn('  미설정 — https://data.seoul.go.kr/together/mypage/actkeyMain.do 에서 [일반 인증키] 발급');

            return false;
        }

        $period = Period::quarter($this->option('yq') ?: $this->quarter());

        try {
            // 1건만 받아 키와 서비스명이 맞는지 본다.
            $result = $client->fetchPage('VwsmAdstrdSelngW', $period, 1, 1);
        } catch (\Throwable $e) {
            $this->error('  실패 — '.$e->getMessage());

            return false;
        }

        if ($result['rows'] === []) {
            $this->warn("  키는 통했지만 {$period->label()} 데이터가 비어 있습니다. 다른 분기를 지정해 보세요 (--yq=20241).");

            return true;
        }

        $sample = $result['rows'][0];
        $this->info(sprintf(
            '  정상 — %s 총 %s행 (예: %s %s)',
            $period->label(),
            number_format($result['total']),
            $sample['ADSTRD_CD_NM'] ?? '?',
            $sample['SVC_INDUTY_CD_NM'] ?? '?'
        ));

        return true;
    }

    private function checkSbiz(StoreCollector $collector): bool
    {
        $this->line('<options=bold>소상공인 상가(상권)정보</> (OPENDATA_SERVICE_KEY)');

        if (! $collector->hasKey()) {
            $this->warn('  미설정 — https://www.data.go.kr/data/15012005/openapi.do 에서 활용신청 (자동승인)');

            return false;
        }

        $key = config('sbiz.service_key') ?: config('opendata.service_key');

        $response = Http::timeout(20)
            ->withOptions(['verify' => false])
            ->get(rtrim((string) config('sbiz.base_url'), '/').'/storeListInDong', [
                'serviceKey' => $key,
                'divId' => 'adongCd',
                'key' => '11500603',
                'pageNo' => 1,
                'numOfRows' => 1,
                'type' => 'json',
            ]);

        $body = $response->body();

        if ($response->failed()) {
            $this->error("  실패 — HTTP {$response->status()} ".mb_substr($body, 0, 160));

            return false;
        }

        // 활용신청 전에는 SERVICE_KEY_IS_NOT_REGISTERED_ERROR 가 온다.
        if (str_contains($body, 'SERVICE_KEY_IS_NOT_REGISTERED')) {
            $this->error('  실패 — 이 API 에 대한 활용신청이 아직 없습니다.');
            $this->line('         https://www.data.go.kr/data/15012005/openapi.do 에서 [활용신청]을 눌러주세요.');

            return false;
        }

        if (str_contains($body, 'SERVICE_ACCESS_DENIED') || str_contains($body, 'LIMITED_NUMBER_OF_SERVICE_REQUESTS')) {
            $this->error('  실패 — '.mb_substr(strip_tags($body), 0, 200));

            return false;
        }

        $json = $response->json() ?? [];
        $count = data_get($json, 'body.totalCount') ?? data_get($json, 'response.body.totalCount');

        $this->info('  정상'.($count !== null ? " — 행정동 11500603 점포 {$count}건" : ''));

        return true;
    }

    /** 직전 분기 코드 */
    private function quarter(): string
    {
        $date = now()->subMonths(3);

        return $date->format('Y').(string) (int) ceil($date->month / 3);
    }
}
