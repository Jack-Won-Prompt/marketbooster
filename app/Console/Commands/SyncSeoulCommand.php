<?php

namespace App\Console\Commands;

use App\Services\OpenData\Seoul\SeoulOpenApiClient;
use App\Services\OpenData\Seoul\SeoulSynchronizer;
use App\Support\Period;
use Illuminate\Console\Command;

class SyncSeoulCommand extends Command
{
    protected $signature = 'seoul:sync
        {type=all : floating_population|card_sales|resident_population|workplace_population|all}
        {--yq= : 기준 연분기 YYYYQ (기본: 직전 분기)}
        {--pages= : 최대 페이지 수 (테스트용)}';

    protected $description = '서울시 상권분석서비스(행정동)를 수집해 통계 테이블에 적재합니다.';

    public function handle(SeoulSynchronizer $synchronizer, SeoulOpenApiClient $client): int
    {
        if (! $client->hasKey()) {
            $this->error('SEOUL_OPENAPI_KEY 가 비어 있습니다.');
            $this->line('  1) https://data.seoul.go.kr 로그인 후 [일반 인증키]를 발급받으세요.');
            $this->line('     https://data.seoul.go.kr/together/mypage/actKeyMain.do');
            $this->line('  2) .env 의 SEOUL_OPENAPI_KEY 에 넣고 php artisan config:clear 를 실행하세요.');
            $this->newLine();
            $this->line('  ※ 서울 열린데이터광장은 API 별 활용신청이 없습니다. 인증키 하나로 아래 서비스를 모두 씁니다.');

            foreach (config('seoul.datasets') as $type => $definition) {
                $this->line(sprintf('     - %-22s %s (%s)', $type, $definition['label'], $definition['service']));
            }

            return self::FAILURE;
        }

        $period = Period::quarter($this->option('yq') ?: $this->previousQuarter());
        $pages = $this->option('pages') ? (int) $this->option('pages') : null;

        $types = $this->argument('type') === 'all'
            ? array_keys(config('seoul.datasets'))
            : [$this->argument('type')];

        $failed = false;

        foreach ($types as $type) {
            $this->newLine();
            $this->info("[{$type}] {$period->label()} 수집을 시작합니다…");

            try {
                $result = $synchronizer->sync(
                    $type,
                    $period,
                    fn (string $message) => $this->line('  '.$message),
                    $pages
                );
            } catch (\Throwable $e) {
                $this->error('  '.$e->getMessage());
                $failed = true;

                continue;
            }

            $this->line(sprintf('  수신 %s행', number_format($result['received'])));

            foreach ($result['imported'] as $bucket => $count) {
                $this->line(sprintf('  저장 %-24s %s건', $bucket, number_format($count)));
            }

            if ($result['skipped'] > 0) {
                $this->line(sprintf('  제외 %s건', number_format($result['skipped'])));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** 직전 분기 코드 (예: 2026년 9월이면 20262) */
    private function previousQuarter(): string
    {
        $date = now()->subMonths(3);

        return $date->format('Y').(string) (int) ceil($date->month / 3);
    }
}
