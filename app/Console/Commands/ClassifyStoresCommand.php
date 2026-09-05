<?php

namespace App\Console\Commands;

use App\Services\Analysis\BenchmarkService;
use App\Services\Stores\StoreClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyStoresCommand extends Command
{
    protected $signature = 'stores:classify
        {--reset : 기존 분류를 지우고 처음부터 다시 매깁니다}';

    protected $description = '점포에 분야(식당·디저트 등)와 프랜차이즈 브랜드를 붙입니다.';

    public function handle(StoreClassifier $classifier): int
    {
        $total = DB::table('stores')->count();

        if ($total === 0) {
            $this->error('점포가 없습니다. php artisan sbiz:sync-stores 로 먼저 수집하세요.');

            return self::FAILURE;
        }

        if ($this->option('reset')) {
            $this->line('기존 분류를 지웁니다…');
            DB::table('stores')->update(['sector' => null, 'brand' => null, 'is_franchise' => false]);
        }

        $this->info(sprintf('점포 %s건을 분류합니다…', number_format($total)));

        $result = $classifier->classify(fn (string $message) => $this->line('  '.$message));

        BenchmarkService::invalidate();

        $franchises = DB::table('stores')->where('is_franchise', true)->count();

        $this->newLine();
        $this->info(sprintf(
            '완료: 브랜드 %s개 / 프랜차이즈 점포 %s건 (%s%%)',
            number_format($result['brands']),
            number_format($franchises),
            number_format($franchises / max(1, $total) * 100, 1)
        ));

        $this->newLine();
        $this->line('분야별 점포 수');

        $rows = DB::table('stores')
            ->selectRaw('sector, COUNT(*) AS stores, SUM(is_franchise) AS franchises')
            ->groupBy('sector')
            ->orderByDesc('stores')
            ->get();

        $this->table(
            ['분야', '점포', '프랜차이즈', '비중'],
            $rows->map(fn ($row) => [
                \App\Support\StoreSectors::label($row->sector),
                number_format($row->stores),
                number_format($row->franchises),
                number_format($row->franchises / max(1, $row->stores) * 100, 1).'%',
            ])->all()
        );

        return self::SUCCESS;
    }
}
