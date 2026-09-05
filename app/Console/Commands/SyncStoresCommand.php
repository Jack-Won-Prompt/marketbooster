<?php

namespace App\Console\Commands;

use App\Models\Region;
use App\Services\OpenData\Sbiz\StoreCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class SyncStoresCommand extends Command
{
    protected $signature = 'sbiz:sync-stores
        {--sido=서울특별시 : 시도명}
        {--sigungu= : 시군구명 (생략하면 시도 전체를 시군구별 프로세스로 나눠 수집)}
        {--dong= : 행정동코드 하나만 수집}
        {--skip-collected : 이미 점포가 수집된 행정동은 건너뜁니다}';

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

        // 시도 전체는 한 프로세스로 돌리면 메모리가 계속 늘어난다.
        // 시군구마다 프로세스를 새로 띄워 메모리를 리셋하고, 한 곳이 실패해도 나머지를 이어간다.
        if (! $this->option('dong') && ! $this->option('sigungu')) {
            return $this->runPerSigungu();
        }

        $codes = $this->option('dong')
            ? [$this->option('dong')]
            : $collector->regionCodesForSigungu($this->option('sido'), $this->option('sigungu'));

        if ($codes === []) {
            $this->error('수집할 행정동이 없습니다. 행정동 마스터를 먼저 적재하셨나요? (php artisan regions:import)');

            return self::FAILURE;
        }

        if ($this->option('skip-collected')) {
            $codes = $this->withoutCollected($codes);

            // 이미 다 모아 둔 곳을 실패로 보면 이어받기 실행이 통째로 실패가 된다.
            if ($codes === []) {
                $this->line('  이미 모두 수집돼 있어 건너뜁니다.');

                return self::SUCCESS;
            }
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

    /** 시도에 속한 시군구를 하나씩 자식 프로세스로 수집한다. */
    private function runPerSigungu(): int
    {
        $sido = $this->option('sido');

        $sigungus = Region::where('sido_name', $sido)
            ->distinct()
            ->orderBy('sigungu_name')
            ->pluck('sigungu_name')
            ->all();

        if ($sigungus === []) {
            $this->error("{$sido} 에 등록된 행정동이 없습니다. php artisan regions:import {$sido} 를 먼저 실행하세요.");

            return self::FAILURE;
        }

        $this->info(sprintf('%s 시군구 %s곳을 순서대로 수집합니다.', $sido, count($sigungus)));
        $failed = [];

        foreach ($sigungus as $index => $sigungu) {
            $this->newLine();
            $this->line(sprintf('[%d/%d] %s', $index + 1, count($sigungus), $sigungu));

            // HTTP 응답 버퍼가 행정동마다 몇 MB씩 쌓여 기본 128M 로는 큰 시군구를 못 넘긴다.
            $command = [
                PHP_BINARY, '-d', 'memory_limit=512M',
                'artisan', 'sbiz:sync-stores', "--sido={$sido}", "--sigungu={$sigungu}",
            ];

            if ($this->option('skip-collected')) {
                $command[] = '--skip-collected';
            }

            $result = Process::path(base_path())
                ->timeout(3600)
                ->run($command, function (string $type, string $line) {
                    $this->output->write($line);
                });

            if ($result->failed()) {
                $failed[] = $sigungu;
                $this->warn("  {$sigungu} 수집 실패 — 계속 진행합니다.");
            }
        }

        $this->newLine();

        if ($failed !== []) {
            $this->warn('실패한 시군구: '.implode(', ', $failed));
            $this->line('  --skip-collected 를 붙여 다시 실행하면 남은 곳만 이어서 수집합니다.');

            return self::FAILURE;
        }

        $this->info("{$sido} 전체 수집을 마쳤습니다.");

        return self::SUCCESS;
    }

    /**
     * 이미 점포가 들어있는 행정동을 제외한다. 중단된 수집을 이어서 돌릴 때 쓴다.
     *
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private function withoutCollected(array $codes): array
    {
        $collected = DB::table('stores')
            ->whereIn('region_code', $codes)
            ->distinct()
            ->pluck('region_code')
            ->all();

        return array_values(array_diff($codes, $collected));
    }
}
