<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataImportLog;
use App\Models\DataSource;
use App\Services\OpenData\DatasetWriter;
use App\Services\OpenData\PublicDataClient;
use App\Support\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DataController extends Controller
{
    /** 어떤 통계가 어느 기준월까지 쌓여 있는지 한눈에 보여준다. */
    public function index(PublicDataClient $client): View
    {
        $tables = [
            'resident_populations' => '거주 인구(추정)',
            'households' => '배후세대',
            'workplace_populations' => '직장인구',
            'floating_populations' => '유동인구',
            'card_sales' => '카드매출',
            'card_sales_demographics' => '카드매출(성·연령)',
            'students' => '학생 수',
            'academies' => '학원 수',
        ];

        $coverage = [];

        foreach ($tables as $table => $label) {
            $coverage[] = [
                'table' => $table,
                'label' => $label,
                'rows' => DB::table($table)->count(),
                'regions' => DB::table($table)->distinct()->count('region_code'),
                'latest' => $this->latestLabel($table),
                'periods' => $this->periodCount($table),
            ];
        }

        return view('admin.data', [
            'storeCount' => DB::table('stores')->count(),
            'seoulDatasets' => config('seoul.datasets'),
            'hasSeoulKey' => filled(config('seoul.api_key')),
            'coverage' => $coverage,
            'sources' => DataSource::orderBy('sort_order')->get(),
            'logs' => DataImportLog::latest()->limit(20)->get(),
            'hasServiceKey' => $client->hasKey(),
            'importTypes' => DatasetWriter::types(),
        ]);
    }

    /** 가장 최근 기준 기간을 사람이 읽을 수 있는 라벨로 (분기 우선) */
    private function latestLabel(string $table): string
    {
        $quarter = DB::table($table)->where('base_yq', '!=', '')->max('base_yq');

        if ($quarter) {
            return Period::quarter($quarter)->label();
        }

        $month = DB::table($table)->where('base_ym', '!=', '')->max('base_ym');

        return $month ? Period::month($month)->label() : '-';
    }

    /** 적재된 기간이 몇 개인지 (월 + 분기) */
    private function periodCount(string $table): int
    {
        return DB::table($table)->where('base_yq', '!=', '')->distinct()->count('base_yq')
            + DB::table($table)->where('base_ym', '!=', '')->distinct()->count('base_ym');
    }
}
