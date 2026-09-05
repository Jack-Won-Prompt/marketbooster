<?php

namespace App\Http\Controllers;

use App\Services\Analysis\StatisticsRepository;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 위치 상권 현황 — 지도에서 지점을 찍으면 그 반경의 상권을 바로 보여 준다.
 */
class MarketMapController extends Controller
{
    public function __construct(private readonly StatisticsRepository $stats) {}

    public function index(Request $request): View
    {
        $periods = $this->stats->availablePeriods();

        return view('map.index', [
            'periods' => $periods,
            'defaultPeriod' => $periods[0] ?? Period::month(now()->subMonth()->format('Ym')),
            'radiusOptions' => [150, 300, 600, 1000, 1500, 3000],
            'defaultRadius' => (int) config('map.default_radius', 1000),
            'defaultCenter' => config('map.default_center'),
            'favorites' => $request->user()->favoriteRegions()->with('region')->get(),
        ]);
    }
}
