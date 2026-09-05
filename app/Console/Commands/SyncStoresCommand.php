<?php

namespace App\Console\Commands;

use App\Services\OpenData\Sbiz\StoreCollector;
use Illuminate\Console\Command;

class SyncStoresCommand extends Command
{
    protected $signature = 'sbiz:sync-stores
        {--sido=서울특별시 : 시도명}
        {--sigungu= : 시군구명 (생략하면 시도 전체)}
        {--dong= : 행정동코드 하나만 수집}';

    protected $description = '소상공인시장진흥공단 상가(상권)정보를 행정동 단위로 수집합니다.';

    public function handle(StoreCollector $collector): int
    {
        if (! $collector->hasKey()) {
            $this->error('상가정보 API 인증키가 없습니다.');
            $this->line('  1) https://www.data.go.kr/data/15012005/openapi.do 에서 활용신청 (자동승인)');
            $this->line('  2) 발급받은 Decoding 키를 .env 의 OPENDATA_SERVICE_KEY 에 넣으세요.');
            $this->line('  3) php artisan config:clear 후 다시 실행하세요.');

            return self::FAILURE;
        }

        $codes = $this->option('dong')
            ? [$this->option('dong')]
            : $collector->regionCodesForSigungu($this->option('sido'), $this->option('sigungu'));

        if ($codes === []) {
            $this->error('수집할 행정동이 없습니다. RegionSeeder 를 먼저 실행하셨나요?');

            return self::FAILURE;
        }

        $this->info(sprintf('행정동 %s곳의 상가업소를 수집합니다…', number_format(count($codes))));

        try {
            $result = $collector->collectRegions($codes, fn (string $m) => $this->line($m));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            '완료: 행정동 %s곳 / 수신 %s건 / 저장 %s건',
            number_format($result['regions']),
            number_format($result['received']),
            number_format($result['imported'])
        ));

        return self::SUCCESS;
    }
}
