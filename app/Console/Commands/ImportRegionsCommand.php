<?php

namespace App\Console\Commands;

use App\Services\Regions\HangJeongDongImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportRegionsCommand extends Command
{
    protected $signature = 'regions:import
        {sido?* : 적재할 시도명 (예: 경기도). 생략하면 전국}
        {--file= : 행정동 경계 GeoJSON 경로 (기본 storage/app/seed/hangjeongdong_kr.geojson)}
        {--download : 파일이 없거나 --force 일 때 원본을 내려받습니다}
        {--force : 파일이 있어도 다시 내려받습니다}';

    protected $description = '전국 행정동 경계 GeoJSON 에서 시도를 골라 행정동 마스터·경계를 적재합니다.';

    /** 공개 행정동 경계 원본 (행정안전부 행정동 기준, 분기마다 갱신) */
    private const SOURCE_URL = 'https://raw.githubusercontent.com/vuski/admdongkor/master/ver20260701/HangJeongDong_ver20260701.geojson';

    public function handle(): int
    {
        $path = $this->option('file') ?: storage_path('app/seed/hangjeongdong_kr.geojson');

        if (($this->option('force') || ! is_readable($path)) && ($this->option('download') || $this->option('force'))) {
            if (! $this->download($path)) {
                return self::FAILURE;
            }
        }

        if (! is_readable($path)) {
            $this->error("행정동 경계 파일이 없습니다: {$path}");
            $this->line('  php artisan regions:import 경기도 --download 로 내려받을 수 있습니다.');

            return self::FAILURE;
        }

        $sidos = $this->argument('sido');
        $this->info($sidos ? implode(', ', $sidos).' 행정동을 적재합니다…' : '전국 행정동을 적재합니다…');

        $bar = $this->output->createProgressBar();
        $bar->start();

        try {
            $result = (new HangJeongDongImporter($path))->import(
                $sidos,
                function (int $done) use ($bar) {
                    $bar->setProgress($done);
                }
            );
        } catch (\Throwable $e) {
            $bar->finish();
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        foreach ($result['sidos'] as $name => $count) {
            $this->line(sprintf('  %-14s %s개 행정동', $name, number_format($count)));
        }

        $this->newLine();
        $this->info(sprintf(
            '완료: 행정동 %s건 / 경계 %s건',
            number_format($result['regions']),
            number_format($result['boundaries'])
        ));

        if ($result['boundaries'] < $result['regions']) {
            $this->warn(sprintf('경계를 만들지 못한 행정동이 %s건 있습니다.', $result['regions'] - $result['boundaries']));
        }

        return self::SUCCESS;
    }

    private function download(string $path): bool
    {
        @mkdir(dirname($path), 0775, true);
        $this->info('행정동 경계 원본을 내려받습니다… (약 34MB)');

        try {
            $response = Http::timeout(300)->get(self::SOURCE_URL);
        } catch (\Throwable $e) {
            $this->error('내려받기 실패: '.$e->getMessage());

            return false;
        }

        if (! $response->successful()) {
            $this->error('내려받기 실패: HTTP '.$response->status());

            return false;
        }

        file_put_contents($path, $response->body());
        $this->line('  저장: '.$path.' ('.number_format((int) (filesize($path) / 1024 / 1024)).'MB)');

        return true;
    }
}
