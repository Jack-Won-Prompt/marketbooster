<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'stats' => $this->platformStats(),
        ]);
    }

    /** 랜딩 히어로 아래에 노출할 플랫폼 규모 지표 */
    private function platformStats(): array
    {
        return [
            'regions' => Region::count(),
            'floating_rows' => DB::table('floating_populations')->count(),
            'sales_rows' => DB::table('card_sales')->count(),
            'analyses' => Analysis::where('status', 'completed')->count(),
        ];
    }
}
