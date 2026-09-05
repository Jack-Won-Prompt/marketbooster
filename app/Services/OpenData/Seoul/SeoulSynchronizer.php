<?php

namespace App\Services\OpenData\Seoul;

use App\Models\DataImportLog;
use App\Models\DataSource;
use App\Services\OpenData\DatasetWriter;
use App\Support\Period;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 서울시 상권분석서비스를 수집해 내부 통계 테이블에 적재한다.
 *
 * 서울 API 한 건이 여러 내부 테이블로 흩어지므로(예: 추정매출 → card_sales + 성·연령 매출),
 * 변환기가 "종류 => 행 목록" 을 돌려주면 종류별로 DatasetWriter 에 넘긴다.
 */
class SeoulSynchronizer
{
    public function __construct(
        private readonly SeoulOpenApiClient $client,
        private readonly DatasetWriter $writer,
    ) {}

    /**
     * @param  callable(string): void|null  $progress
     * @return array{received:int, imported:array<string,int>, skipped:int}
     */
    public function sync(string $type, Period $period, ?callable $progress = null, ?int $maxPages = null): array
    {
        $definition = config("seoul.datasets.{$type}");

        if (! $definition) {
            throw new RuntimeException(
                "config/seoul.php 에 '{$type}' 정의가 없습니다. (사용 가능: ".implode(', ', array_keys(config('seoul.datasets', []))).')'
            );
        }

        if (! $period->isQuarter()) {
            throw new RuntimeException('서울시 상권분석서비스는 분기 단위입니다. --yq=20242 처럼 분기 코드를 지정해 주세요.');
        }

        /** @var Transformers\SeoulRowTransformer $transformer */
        $transformer = app($definition['transformer']);

        $log = DataImportLog::start($type, 'seoul-api', $period, $definition['service']);
        $imported = [];
        $skipped = 0;

        try {
            $received = $this->client->each(
                $definition['service'],
                $period,
                function (array $rows, int $page) use ($transformer, $period, &$imported, &$skipped, $progress) {
                    $buckets = [];

                    foreach ($rows as $row) {
                        foreach ($transformer->transform($row, $period) as $bucket => $bucketRows) {
                            foreach ($bucketRows as $bucketRow) {
                                $buckets[$bucket][] = $bucketRow;
                            }
                        }
                    }

                    foreach ($buckets as $bucket => $bucketRows) {
                        if ($bucket === 'industries') {
                            $this->upsertIndustries($bucketRows);

                            continue;
                        }

                        $result = $this->writer->write($bucket, $bucketRows);
                        $imported[$bucket] = ($imported[$bucket] ?? 0) + $result['imported'];
                        $skipped += $result['skipped'];
                    }

                    $progress && $progress(sprintf(
                        '%d페이지 처리: 원본 %s행 → %s',
                        $page,
                        number_format(count($rows)),
                        collect($buckets)->map(fn ($r, $k) => "{$k} ".number_format(count($r)).'건')->implode(', ')
                    ));
                },
                $maxPages
            );

            $log->succeed(array_sum($imported), $skipped);
            $this->touchDataSources($type, $period, $definition);

            return ['received' => $received, 'imported' => $imported, 'skipped' => $skipped];
        } catch (\Throwable $e) {
            $log->fail($e->getMessage());

            throw $e;
        }
    }

    /**
     * 응답에 실려 온 서비스 업종을 업종 마스터에 반영한다.
     * 이미 있는 업종의 대분류는 건드리지 않는다.
     */
    private function upsertIndustries(array $rows): void
    {
        $now = now();
        $seen = [];

        foreach ($rows as $row) {
            $seen[$row['code']] = $row['name'];
        }

        if ($seen === []) {
            return;
        }

        $existing = DB::table('industries')->whereIn('code', array_keys($seen))->pluck('code')->all();

        $insert = [];

        foreach ($seen as $code => $name) {
            if (in_array($code, $existing, true)) {
                DB::table('industries')->where('code', $code)->update(['name' => $name, 'updated_at' => $now]);

                continue;
            }

            $insert[] = [
                'code' => $code,
                'name' => $name,
                'group_name' => $this->groupFor($code),
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($insert !== []) {
            DB::table('industries')->insert($insert);
        }
    }

    /** 서울 서비스업종 코드 앞자리로 대분류를 추정한다. */
    private function groupFor(string $code): string
    {
        return match (substr($code, 0, 3)) {
            'CS1' => '요식',
            'CS2' => '소매',
            'CS3' => '서비스',
            default => '기타',
        };
    }

    private function touchDataSources(string $type, Period $period, array $definition): void
    {
        // 한 서비스가 여러 내부 종류를 채우므로 함께 갱신한다.
        $related = match ($type) {
            'card_sales' => ['card_sales', 'card_sales_demographics'],
            'resident_population' => ['resident_population', 'households'],
            default => [$type],
        };

        foreach ($related as $key) {
            DataSource::updateOrCreate(['key' => $key], [
                'category' => str_contains($key, 'card') ? 'sales' : 'population',
                'label' => DataSource::where('key', $key)->value('label') ?: $definition['label'],
                'provider' => $definition['provider'],
                'base_ym' => '',
                'base_yq' => $period->code,
                'base_label' => $period->label(),
            ]);
        }
    }
}
