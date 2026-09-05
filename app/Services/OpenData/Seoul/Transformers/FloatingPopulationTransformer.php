<?php

namespace App\Services\OpenData\Seoul\Transformers;

use App\Support\Period;

/**
 * VwsmAdstrdFlpopW — 서울시 상권분석서비스(길단위인구-행정동)
 *
 * 원본은 시간대 6구간 · 요일 7일 · 성별 2 · 연령 6구간을 각각 따로 준다.
 * 우리 floating_populations 는 요일 × 시간대 × 성별 × 연령 교차표이므로 비율을 곱해 채운다.
 */
class FloatingPopulationTransformer extends SeoulRowTransformer
{
    public function transform(array $row, Period $period): array
    {
        $regionCode = $this->regionCode($row);

        if ($regionCode === null) {
            return [];
        }

        $bands = $this->timeBandValues($row, 'TMZON_%s_FLPOP_CO');
        $dayShares = $this->shares($this->dayTypeValues($row, '%s_FLPOP_CO'));
        $genderShares = $this->genderShares(
            $this->num($row, 'ML_FLPOP_CO'),
            $this->num($row, 'FML_FLPOP_CO')
        );
        $ageShares = $this->shares($this->ageValues($row, 'AGRDE_%s_FLPOP_CO'));

        $columns = $period->columns();
        $rows = [];

        foreach ($bands as $band => $bandTotal) {
            if ($bandTotal <= 0) {
                continue;
            }

            foreach ($dayShares as $dayType => $dayShare) {
                // 분기 누적을 평일/주말 각자의 일수로 나눠 하루치로 만든다.
                $daily = $this->perDay($bandTotal * $dayShare, $period, $dayType);

                foreach ($genderShares as $gender => $genderShare) {
                    foreach ($ageShares as $ageBand => $ageShare) {
                        $value = (int) round($daily * $genderShare * $ageShare);

                        if ($value <= 0) {
                            continue;
                        }

                        $rows[] = [
                            'region_code' => $regionCode,
                            'day_type' => $dayType,
                            'time_band' => $band,
                            'gender' => $gender,
                            'age_band' => $ageBand,
                            'population' => $value,
                        ] + $columns;
                    }
                }
            }
        }

        return ['floating_population' => $rows];
    }
}
