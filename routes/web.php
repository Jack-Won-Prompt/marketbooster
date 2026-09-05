<?php

use App\Http\Controllers\Admin\DataController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\Api\MarketPreviewController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FavoriteRegionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MarketMapController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 공개 페이지
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::view('/solution', 'pages.solution')->name('solution');
Route::view('/data', 'pages.data')->name('data');
Route::view('/pricing', 'pages.pricing')->name('pricing');

/*
|--------------------------------------------------------------------------
| 인증
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| 회원 전용
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 지도에서 위치를 찍어 그 자리의 상권을 바로 보는 화면
    Route::get('/map', [MarketMapController::class, 'index'])->name('map');

    Route::get('/analyses', [AnalysisController::class, 'index'])->name('analyses.index');
    Route::get('/analyses/new', [AnalysisController::class, 'create'])->name('analyses.create');
    Route::post('/analyses', [AnalysisController::class, 'store'])->name('analyses.store');
    Route::get('/analyses/{analysis}', [AnalysisController::class, 'show'])->name('analyses.show');
    Route::post('/analyses/{analysis}/rerun', [AnalysisController::class, 'rerun'])->name('analyses.rerun');
    Route::delete('/analyses/{analysis}', [AnalysisController::class, 'destroy'])->name('analyses.destroy');
    Route::get('/analyses/{analysis}/report.pdf', [ReportController::class, 'pdf'])->name('analyses.pdf');

    Route::post('/favorites', [FavoriteRegionController::class, 'store'])->name('favorites.store');
    Route::delete('/favorites/{regionCode}', [FavoriteRegionController::class, 'destroy'])->name('favorites.destroy');

    // 지역 선택 화면에서 쓰는 조회 API
    Route::prefix('api/regions')->name('api.regions.')->group(function () {
        Route::get('search', [RegionController::class, 'search'])->name('search');
        Route::get('sigungu', [RegionController::class, 'sigungu'])->name('sigungu');
        Route::get('dongs', [RegionController::class, 'dongs'])->name('dongs');
        Route::get('preview', [RegionController::class, 'preview'])->name('preview');
        Route::get('market', [MarketPreviewController::class, 'preview'])->name('market');
    });
});

/*
|--------------------------------------------------------------------------
| 관리자
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/data', [DataController::class, 'index'])->name('data');
});
