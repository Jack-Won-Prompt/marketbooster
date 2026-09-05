<?php

namespace App\Jobs;

use App\Models\Analysis;
use App\Services\Analysis\AnalysisRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 분석 건수가 많아지면 QUEUE_CONNECTION 을 database/redis 로 두고 이 잡으로 돌린다.
 * 기본 구성에서는 컨트롤러가 AnalysisRunner 를 직접 호출한다.
 */
class RunMarketAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public Analysis $analysis) {}

    public function handle(AnalysisRunner $runner): void
    {
        $runner->run($this->analysis);
    }
}
