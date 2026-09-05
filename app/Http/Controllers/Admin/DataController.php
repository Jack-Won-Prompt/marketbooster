<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataImportLog;
use App\Models\DataSource;
use App\Services\OpenData\DatasetWriter;
use App\Services\OpenData\PublicDataClient;
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
                'latest_ym' => DB::table($table)->max('base_ym'),
                'oldest_ym' => DB::table($table)->min('base_ym'),
            ];
        }

        return view('admin.data', [
            'coverage' => $coverage,
            'sources' => DataSource::orderBy('sort_order')->get(),
            'logs' => DataImportLog::latest()->limit(20)->get(),
            'hasServiceKey' => $client->hasKey(),
            'importTypes' => DatasetWriter::types(),
        ]);
    }
}
