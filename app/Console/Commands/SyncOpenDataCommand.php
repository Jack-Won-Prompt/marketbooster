<?php

namespace App\Console\Commands;

use App\Services\OpenData\DatasetSynchronizer;
use App\Services\OpenData\PublicDataClient;
use App\Support\Period;
use Illuminate\Console\Command;

class SyncOpenDataCommand extends Command
{
    protected $signature = 'opendata:sync
        {type : 데이터 종류 (floating_population|card_sales|resident_population)}
        {--ym= : 기준 기간 YYYYMM(월) 또는 YYYYQ(분기) (기본: 전월)}
        {--pages= : 최대 페이지 수 (테스트용)}';

    protected $description = '공공데이터포털 오픈 API 로 지역 통계를 수집해 저장합니다.';

    public function handle(DatasetSynchronizer $synchronizer, PublicDataClient $client): int
    {
        $type = $this->argument('type');
        $period = Period::parse($this->option('ym') ?: now()->subMonth()->format('Ym'));
        $pages = $this->option('pages') ? (int) $this->option('pages') : null;

        if (! $client->hasKey()) {
            $this->error('OPENDATA_SERVICE_KEY 가 비어 있습니다.');
            $this->line('  1) https://www.data.go.kr 에서 활용신청 후 인증키(Decoding)를 발급받으세요.');
            $this->line('  2) .env 의 OPENDATA_SERVICE_KEY 에 넣고 php artisan config:clear 를 실행하세요.');
            $this->line('  3) API 대신 CSV 파일이 있다면 php artisan opendata:import 를 사용하세요.');

            return self::FAILURE;
        }

        $this->info("[{$type}] {$period->label()} 수집을 시작합니다…");

        try {
            $result = $synchronizer->sync(
                $type,
                $period,
                [],
                fn (string $message) => $this->line('  '.$message),
                $pages
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            '완료: 수신 %s건 / 저장 %s건 / 제외 %s건',
            number_format($result['received']),
            number_format($result['imported']),
            number_format($result['skipped'])
        ));

        return self::SUCCESS;
    }
}
