<?php

namespace App\Console\Commands;

use App\Services\OpenData\CsvImporter;
use App\Services\OpenData\DatasetWriter;
use Illuminate\Console\Command;

class ImportOpenDataCommand extends Command
{
    protected $signature = 'opendata:import
        {type : 데이터 종류}
        {file : CSV 파일 경로}
        {--ym= : CSV 에 기준연월 열이 없을 때 사용할 YYYYMM}';

    protected $description = '공공데이터포털 파일데이터(CSV)를 읽어 통계 테이블에 적재합니다.';

    public function handle(CsvImporter $importer): int
    {
        $type = $this->argument('type');
        $file = $this->argument('file');

        if (! in_array($type, DatasetWriter::types(), true)) {
            $this->error("알 수 없는 데이터 종류입니다: {$type}");
            $this->line('사용 가능: '.implode(', ', DatasetWriter::types()));

            return self::FAILURE;
        }

        $this->info("[{$type}] {$file} 적재를 시작합니다…");

        try {
            $result = $importer->import(
                $type,
                $file,
                $this->option('ym'),
                fn (string $message) => $this->line('  '.$message)
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            '완료: 읽은 행 %s / 저장 %s건 / 제외 %s건',
            number_format($result['rows']),
            number_format($result['imported']),
            number_format($result['skipped'])
        ));

        return self::SUCCESS;
    }
}
