<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $analyses = $user->analyses()->limit(6)->get();

        return view('dashboard', [
            'analyses' => $analyses,
            'totalAnalyses' => $user->analyses()->count(),
            'completedAnalyses' => $user->analyses()->where('status', 'completed')->count(),
            'favorites' => $user->favoriteRegions()->with('region')->limit(8)->get(),
            'regionCount' => Region::count(),
            'sources' => DataSource::orderBy('sort_order')->get(),
        ]);
    }
}
