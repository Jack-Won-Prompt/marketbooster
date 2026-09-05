<?php

namespace App\Services\OpenData;

use App\Models\DataImportLog;
use App\Models\DataSource;
use App\Support\Period;
use RuntimeException;

/**
 * config/opendata.php 에 정의된 데이터셋을 API 로 수집해 DB 에 적재한다.
 */
class DatasetSynchronizer
{
    public function __construct(
        private readonly PublicDataClient $client,
        private readonly RecordNormalizer $normalizer,
        private readonly DatasetWriter $writer,
    ) {}

    /**
     * @param  callable(string): void|null  $progress
     * @return array{imported:int, skipped:int, received:int}
     */
    public function sync(string $type, Period $period, array $extraParams = [], ?callable $progress = null, ?int $maxPages = null): array
    {
        $definition = config("opendata.datasets.{$type}");

        if (! $definition) {
            throw new RuntimeException("config/opendata.php 에 '{$type}' 데이터셋 정의가 없습니다.");
        }

        if (blank($definition['url'] ?? null)) {
            throw new RuntimeException("'{$type}' 데이터셋의 엔드포인트(url)가 비어 있습니다. .env 또는 config/opendata.php 를 확인하세요.");
        }

        $log = DataImportLog::start($type, 'api', $period, $definition['url']);
        $imported = 0;
        $skipped = 0;

        try {
            $received = $this->client->each(
                $definition['url'],
                array_merge($definition['params'] ?? [], ['STDR_YM' => $period->code, 'stdrYm' => $period->code], $extraParams),
                $definition['items_path'] ?? 'response.body.items.item',
                function (array $items, int $page) use ($type, $definition, &$imported, &$skipped, $progress) {
                    $rows = array_map(
                        fn (array $row) => $this->normalizer->apply($row, $definition['map'] ?? []),
                        $items
                    );

                    $result = $this->writer->write($type, $rows);
                    $imported += $result['imported'];
                    $skipped += $result['skipped'];

                    $progress && $progress("{$page}페이지 처리: 저장 {$result['imported']}건 / 제외 {$result['skipped']}건");
                },
                $maxPages
            );

            $log->succeed($imported, $skipped);
            $this->touchDataSource($type, $period, $definition['label'] ?? $type);

            return ['imported' => $imported, 'skipped' => $skipped, 'received' => $received];
        } catch (\Throwable $e) {
            $log->fail($e->getMessage());

            throw $e;
        }
    }

    /** 리포트 마지막 장의 "데이터 출처" 기준월을 갱신한다. */
    public function touchDataSource(string $type, Period $period, string $label): void
    {
        $source = DataSource::firstOrNew(['key' => $type]);
        $source->fill([
            'category' => str_contains($type, 'card') ? 'sales' : (in_array($type, ['students', 'academies'], true) ? 'education' : 'population'),
            'label' => $source->label ?: $label,
            'provider' => $source->provider ?: '공공데이터포털',
            'base_ym' => $period->isQuarter() ? '' : $period->code,
            'base_yq' => $period->isQuarter() ? $period->code : '',
            'base_label' => $period->label(),
        ])->save();
    }
}
