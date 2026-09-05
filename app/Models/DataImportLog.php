<?php

namespace App\Models;

use App\Support\Period;
use Illuminate\Database\Eloquent\Model;

class DataImportLog extends Model
{
    protected $fillable = [
        'type', 'channel', 'base_ym', 'base_yq', 'reference', 'rows_imported', 'rows_skipped',
        'status', 'message', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public static function start(string $type, string $channel, ?Period $period, ?string $reference = null): self
    {
        return static::create([
            'type' => $type,
            'channel' => $channel,
            'base_ym' => $period && ! $period->isQuarter() ? $period->code : '',
            'base_yq' => $period && $period->isQuarter() ? $period->code : '',
            'reference' => $reference,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function succeed(int $imported, int $skipped = 0, ?string $message = null): void
    {
        $this->update([
            'status' => 'success',
            'rows_imported' => $imported,
            'rows_skipped' => $skipped,
            'message' => $message,
            'finished_at' => now(),
        ]);
    }

    public function fail(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'message' => mb_substr($message, 0, 2000),
            'finished_at' => now(),
        ]);
    }
}
