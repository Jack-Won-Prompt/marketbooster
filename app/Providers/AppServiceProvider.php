<?php

namespace App\Providers;

use App\Services\OpenData\PublicDataClient;
use App\Services\OpenData\Sbiz\StoreCollector;
use App\Services\OpenData\Seoul\SeoulOpenApiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * 오픈 API 클라이언트들은 생성자 인자가 모두 기본값(null)이라
         * 컨테이너가 그냥 new 하면 인증키 없이 만들어진다.
         * 반드시 설정에서 읽어 만들도록 바인딩한다.
         */
        $this->app->singleton(PublicDataClient::class, fn () => PublicDataClient::fromConfig());
        $this->app->singleton(SeoulOpenApiClient::class, fn () => SeoulOpenApiClient::fromConfig());
        $this->app->singleton(StoreCollector::class, fn () => StoreCollector::fromConfig());
    }

    public function boot(): void
    {
        //
    }
}
