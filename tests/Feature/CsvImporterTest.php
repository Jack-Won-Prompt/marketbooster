<?php

namespace Tests\Feature;

use App\Services\OpenData\CsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CsvImporterTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(string $contents, string $encoding = 'UTF-8'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');

        file_put_contents(
            $path,
            $encoding === 'UTF-8' ? $contents : mb_convert_encoding($contents, $encoding, 'UTF-8')
        );

        return $path;
    }

    public function test_한글_헤더_CSV_를_읽어_적재한다(): void
    {
        $path = $this->writeCsv(<<<'CSV'
        행정동코드,기준연월,요일구분,시간대,성별,연령대,유동인구수
        1150053000,202608,평일,점심,남,30대,1200
        1150053000,202608,평일,점심,여,30대,1300
        CSV);

        $result = app(CsvImporter::class)->import('floating_population', $path);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);

        $row = DB::table('floating_populations')->where('gender', 'M')->first();

        $this->assertSame('1150053000', $row->region_code);
        $this->assertSame('202608', $row->base_ym);
        $this->assertSame('weekday', $row->day_type);
        $this->assertSame('lunch', $row->time_band);
        $this->assertSame('30s', $row->age_band);
        $this->assertSame(1200, $row->population);

        unlink($path);
    }

    public function test_CP949_로_저장된_CSV_도_읽는다(): void
    {
        $path = $this->writeCsv(<<<'CSV'
        행정동코드,기준연월,주거유형,세대수
        1150053000,202608,아파트,5269
        1150053000,202608,오피스텔,16958
        CSV, 'CP949');

        $result = app(CsvImporter::class)->import('households', $path);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(16958, DB::table('households')->where('housing_type', 'officetel')->value('households'));

        unlink($path);
    }

    public function test_같은_키를_다시_넣으면_덮어쓴다(): void
    {
        $first = $this->writeCsv(<<<'CSV'
        행정동코드,기준연월,주거유형,세대수
        1150053000,202608,아파트,100
        CSV);

        $second = $this->writeCsv(<<<'CSV'
        행정동코드,기준연월,주거유형,세대수
        1150053000,202608,아파트,999
        CSV);

        app(CsvImporter::class)->import('households', $first);
        app(CsvImporter::class)->import('households', $second);

        $this->assertSame(1, DB::table('households')->count());
        $this->assertSame(999, DB::table('households')->value('households'));

        unlink($first);
        unlink($second);
    }

    public function test_필수값이_빠진_행은_건너뛴다(): void
    {
        $path = $this->writeCsv(<<<'CSV'
        행정동코드,기준연월,주거유형,세대수
        ,202608,아파트,100
        1150053000,202608,,200
        1150053000,202608,빌라,300
        CSV);

        $result = app(CsvImporter::class)->import('households', $path);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['skipped']);

        unlink($path);
    }

    public function test_수집_이력이_남는다(): void
    {
        $path = $this->writeCsv(<<<'CSV'
        행정동코드,기준연월,주거유형,세대수
        1150053000,202608,아파트,100
        CSV);

        app(CsvImporter::class)->import('households', $path, '202608');

        $log = DB::table('data_import_logs')->first();

        $this->assertSame('households', $log->type);
        $this->assertSame('csv', $log->channel);
        $this->assertSame('success', $log->status);
        $this->assertSame(1, $log->rows_imported);

        unlink($path);
    }
}
