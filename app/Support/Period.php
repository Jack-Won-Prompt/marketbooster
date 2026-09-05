<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * 통계의 기준 기간.
 *
 * 출처마다 집계 주기가 다르다.
 *   - 행정안전부 주민등록인구, 학원 인허가 …  월 단위      (YYYYMM)
 *   - 서울시 상권분석서비스                     분기 단위    (YYYYQ, 예: 20242)
 *
 * 두 주기를 억지로 한쪽으로 변환하면 원본이 훼손되므로,
 * 통계 테이블은 base_ym(월) 과 base_yq(분기) 두 칸을 두고 쓰지 않는 쪽은 빈 문자열로 남긴다.
 * 이 클래스가 "어느 칸으로 걸러야 하는지"를 한 곳에서 결정한다.
 */
class Period
{
    public const MONTH = 'month';

    public const QUARTER = 'quarter';

    private function __construct(
        public readonly string $type,
        public readonly string $code,
    ) {}

    public static function month(string $code): self
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            throw new InvalidArgumentException("월 기준 코드는 YYYYMM 형식이어야 합니다: {$code}");
        }

        return new self(self::MONTH, $code);
    }

    public static function quarter(string $code): self
    {
        if (! preg_match('/^\d{4}[1-4]$/', $code)) {
            throw new InvalidArgumentException("분기 기준 코드는 YYYYQ 형식이어야 합니다: {$code}");
        }

        return new self(self::QUARTER, $code);
    }

    /** 길이로 종류를 판별한다. 5자리면 분기, 6자리면 월. */
    public static function parse(string $code): self
    {
        $code = trim($code);

        return strlen($code) === 5 ? self::quarter($code) : self::month($code);
    }

    /** 저장된 두 칸(base_ym, base_yq)에서 기간을 복원한다. */
    public static function fromColumns(?string $baseYm, ?string $baseYq): self
    {
        return filled($baseYq) ? self::quarter($baseYq) : self::month((string) $baseYm);
    }

    public function isQuarter(): bool
    {
        return $this->type === self::QUARTER;
    }

    /** 통계 테이블에 저장할 두 칸의 값 */
    public function columns(): array
    {
        return $this->isQuarter()
            ? ['base_ym' => '', 'base_yq' => $this->code]
            : ['base_ym' => $this->code, 'base_yq' => ''];
    }

    /** 조회 시 걸러야 할 칸과 값 */
    public function filterColumn(): string
    {
        return $this->isQuarter() ? 'base_yq' : 'base_ym';
    }

    /** "2024년 2분기" 또는 "2026년 8월" */
    public function label(): string
    {
        if ($this->isQuarter()) {
            return sprintf('%s년 %d분기', substr($this->code, 0, 4), (int) substr($this->code, 4, 1));
        }

        return Carbon::createFromFormat('Ym', $this->code)->format('Y년 n월');
    }

    /** 캐시 키·파일명 등에 쓸 짧은 식별자 */
    public function key(): string
    {
        return $this->isQuarter() ? "q{$this->code}" : "m{$this->code}";
    }

    /** 기간의 시작일(포함)과 끝일(제외) */
    public function range(): array
    {
        if (! $this->isQuarter()) {
            $start = Carbon::createFromFormat('Ym', $this->code)->startOfMonth();

            return [$start, $start->copy()->addMonth()];
        }

        $year = (int) substr($this->code, 0, 4);
        $quarter = (int) substr($this->code, 4, 1);
        $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();

        return [$start, $start->copy()->addMonths(3)];
    }

    /**
     * 이 기간이 며칠인지.
     *
     * 서울시 상권분석서비스처럼 기간 동안 누적된 값(유동인구·매출)을
     * 일평균으로 환산할 때 나누는 수다.
     */
    public function days(): int
    {
        $counts = $this->dayCounts();

        return $counts['weekday'] + $counts['weekend'];
    }

    /**
     * 기간 안의 평일 수와 주말 수.
     *
     * 누적값을 일평균으로 바꿀 때 전체 일수로 나누면 안 된다.
     * 한 분기는 평일이 약 65일, 주말이 약 26일이라 평일 누적을 91로 나누면
     * 평일 하루 값이 3분의 1 수준으로 과소평가된다.
     *
     * @return array{weekday:int, weekend:int}
     */
    public function dayCounts(): array
    {
        [$start, $end] = $this->range();

        $weekday = 0;
        $weekend = 0;

        for ($day = $start->copy(); $day->lt($end); $day->addDay()) {
            $day->isWeekend() ? $weekend++ : $weekday++;
        }

        return ['weekday' => $weekday, 'weekend' => $weekend];
    }

    /** 분기의 마지막 달 (월 단위 데이터와 나란히 볼 때 참고용) */
    public function approximateMonth(): string
    {
        if (! $this->isQuarter()) {
            return $this->code;
        }

        return substr($this->code, 0, 4).str_pad((string) ((int) substr($this->code, 4, 1) * 3), 2, '0', STR_PAD_LEFT);
    }

    public function equals(?self $other): bool
    {
        return $other !== null && $other->type === $this->type && $other->code === $this->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
