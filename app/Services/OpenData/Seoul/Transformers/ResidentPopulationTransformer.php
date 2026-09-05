<?php

namespace App\Services\OpenData\Seoul\Transformers;

use App\Support\Period;

/**
 * VwsmAdstrdRepopW — 서울시 상권분석서비스(상주인구-행정동)
 *
 * 이 서비스는 성별 × 연령 교차값(MAG_/FAG_)을 직접 준다. 추정이 필요 없다.
 * 총 가구 수(아파트 / 비아파트)도 같은 행에 들어 있어 배후세대까지 함께 담는다.
 */
class ResidentPopulationTransformer extends SeoulRowTransformer
{
    public function transform(array $row, Period $period): array
    {
        $regionCode = $this->regionCode($row);

        if ($regionCode === null) {
            return [];
        }

        $columns = $period->columns();
        $population = [];

        foreach (self::AGE_BANDS as $suffix => $ageBand) {
            foreach (['M' => 'MAG', 'F' => 'FAG'] as $gender => $prefix) {
                $value = (int) round($this->num($row, "{$prefix}_{$suffix}_REPOP_CO"));

                if ($value <= 0) {
                    continue;
                }

                $population[] = [
                    'region_code' => $regionCode,
                    'gender' => $gender,
                    'age_band' => $ageBand,
                    'population' => $value,
                ] + $columns;
            }
        }

        $households = [];

        foreach (['apartment' => 'APT_HSHLD_CO', 'non_apartment' => 'NON_APT_HSHLD_CO'] as $type => $field) {
            $value = (int) round($this->num($row, $field));

            if ($value <= 0) {
                continue;
            }

            $households[] = [
                'region_code' => $regionCode,
                'housing_type' => $type,
                'households' => $value,
            ] + $columns;
        }

        return [
            'resident_population' => $population,
            'households' => $households,
        ];
    }
}
