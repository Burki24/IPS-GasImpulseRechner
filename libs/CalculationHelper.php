<?php

declare(strict_types=1);

trait CalculationHelper
{
    // Umrechnung m3 in kwh
    private function calculateKWH($calorific_value, $cubic_meter, $condition_number)
    {
        $kwh = $calorific_value * $cubic_meter * $condition_number;
        $this->SetValue('GCM_UsedKWH', $kwh);
        return $kwh;
    }

    // Grundpreisperiode berechnen
    private function calculatePeriod($value, $period, $months, $invoice_date)
    {
        $days_in_year = (int) date('L') ? 366 : 365;
        if ($months != 12) {
            $days_in_year = (int) date('L', strtotime('+2 months')) ? 396 : 395;
        }
        switch ($period) {
            case 'year':
                $daysInPeriod = $days_in_year;
                break;
            case 'half_year':
                $daysInPeriod = $days_in_year / 2;
                break;
            case 'quarter_year':
                $daysInPeriod = $days_in_year / 4;
                break;
            case 'month':
                $daysInPeriod = $days_in_year / 12;
                break;
            case 'day':
                $daysInPeriod = 1;
                break;
            default:
                throw new InvalidArgumentException('Invalid period provided.');
        }
        return $value / $daysInPeriod;
    }

    // Kosten seit Abrechnung
    private function calculateCosts($base_price, $invoice_date, $current_kwh_consumption, $kwh_price)
    {
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $time_stampPlusOneYear = (new DateTimeImmutable())->setTimestamp($time_stamp)->add(new DateInterval('P1Y'))->getTimestamp();
        $days_since = floor((time() - $time_stamp) / (60 * 60 * 24));
        $days_until = abs(floor((time() - $time_stampPlusOneYear) / (60 * 60 * 24)));
        $baseCosts = round($base_price * $days_since, 2);
        $kwh = round($current_kwh_consumption, 2);
        $kwhCosts = round($kwh * $kwh_price, 2);
        $costs = round($kwhCosts + $baseCosts, 2);

        if ($days_since > 0) {
            $days_total = $days_since + $days_until;
            $costs_forecast = ($days_total * $base_price) + (($costs / $days_since) * $days_total);
            $this->SetValue('GCM_CostsSinceInvoice', $costs);
            $this->SetValue('GCM_DaysSinceInvoice', $days_since);
        }
        return $costs;
    }

    //Kosten
    private function calculatForecast($invoice_date, $base_price, $calorific_value, $current_kwh_consumption, $kwh_price, $condition_number)
    {
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $time_stamp_plus_one_year = (new DateTimeImmutable())->setTimestamp($time_stamp)->add(new DateInterval('P1Y'))->getTimestamp();
        $days_since = floor((time() - $time_stamp) / (60 * 60 * 24));
        $days_until = abs(floor((time() - $time_stamp_plus_one_year) / (60 * 60 * 24)));
        $base_costs = $base_price * $days_until;
        $kwh = $current_kwh_consumption;
        $kwh_costs = $kwh * $kwh_price;
        $costs = $kwh_costs + $base_costs;
        $days_total = $days_since + $days_until;
        $costs_forecast = ($days_total * $base_price) + (($costs / $days_since) * $days_total);
        $kwh_forecast = (($kwh / $days_since) * $days_total);
        $this->SetValue('GCM_DaysTillInvoice', $days_until);
        $this->SetValue('GCM_CostsForecast', $costs_forecast);
        $this->SetValue('GCM_kwhForecast', $kwh_forecast);
        return [
            'kwh_day_difference'  => $days_until,
            'calculated_forecast' => $costs_forecast,
            'monthly_forecast'    => $kwh_forecast,
        ];
    }

    // Berechnung Differenz zwischen m3 Rechnungsstellung und Aktuell
    private function DifferenceFromInvoice($actual_counter_value, $invoice_count, $calorific_value, $condition_number)
    {
        $result = ($actual_counter_value - $invoice_count);
        $kwh = ($result * $calorific_value * $condition_number);
        $this->SetValue('GCM_CurrentConsumption', $result);
        $this->Setvalue('GCM_KWHSinceInvoice', $kwh);
    }

    // Kosten aktueller Tag
    private function CalculateCostActualDay($base_price, $calorific_value, $kwh_day, $kwh_price, $condition_number)
    {
        $kwhCosts = $kwh_day * $kwh_price * $condition_number;
        $costs = $kwhCosts + $base_price;
        $this->SetValue('GCM_DayCosts', $costs);
    }

    // Aktuelles Datum berechnen
    private function GetCurrentDate()
    {
        $date = date('Y-m-d');
        list($year, $month, $day) = explode('-', $date);
        $dateArray = [
            'year'  => (int) $year,
            'month' => (int) $month,
            'day'   => (int) $day
        ];
        return json_encode($dateArray);
    }
    // Abschlagsberechnungen
    // Höhe der Abschlagszahlung im laufenden Jahr
    private function LumpSumYear($months, $lump_sum, $old_lump_sum, $invoice_date)
    {
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $date_string = date('Y-m-d', $time_stamp);
        $current_date = date('Y-m-d');
        $diff = date_diff(date_create($date_string), date_create($current_date));
        $months_since_invoice = $diff->m + ($diff->d >= 1 ? 1 : 0);

        if ($old_lump_sum == 0) {
            $result = $months * $lump_sum;
        } else {
            $old_lump_sum_result = $months_since_invoice * $old_lump_sum;
            $remaining_months = $months - $months_since_invoice;
            $new_lump_sum_result = $remaining_months * $lump_sum;
            $result = $old_lump_sum_result + $new_lump_sum_result;
        }

        return $result;
    }

    // Differenz zu erwartenden Kosten
    private function LumpSumDifference($lump_sum_year, $costs_forecast)
    {
        $result = $lump_sum_year - $costs_forecast;
        return $result;
    }

    // Bisher gezahlte Abschläge
    private function LumpSumPast($lump_sum, $invoice_date, $months)
    {
        $days_in_year = (int) date('L') ? 366 : 365;
        if ($months != 12) {
            $days_in_year = (int) date('L', strtotime('+2 months')) ? 396 : 395;
        }
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $months_since = ((date('Y') - $date['year']) * 12) + (date('m') - $date['month']);
        $result = $lump_sum * $months_since;
        return $result;
    }

    // KWH Forecast
    private function ForecastKWH($invoice_kwh, $invoice_date, $actual_kwh, $month_factor)
    {
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $months_since = ((date('Y') - $date['year']) * 12) + (date('m') - $date['month']);
        $days_since = floor((time() - $time_stamp) / (60 * 60 * 24));

        $actual_day_kwh = 0;
        if ($actual_kwh != 0 && $months_since != 0) {
            $actual_day_kwh = $actual_kwh / $days_since;
        }

        $weights = json_decode($month_factor, true);
        $total_weight = array_sum(array_column($weights, 'Factor'));

        $last_month = intval($date['month']);
        $last_year = intval($date['year']);
        $today = getdate();
        $current_month = intval($today['mon']);
        $current_year = intval($today['year']);
        $months_since_invoice = ($current_year - $last_year) * 12 + $current_month - $last_month;

        $monthly_forecast = [];
        $calculated_forecast = 0;

        for ($i = 0; $i <= $months_since_invoice; $i++) {
            $current_month = ($last_month + $i - 1) % 12 + 1;
            $current_year = $last_year + (int) (($last_month + $i - 1) / 12);

            if ($current_year == $today['year']) {
                $days_in_month = date('t', mktime(0, 0, 0, $current_month, 1, $current_year)); // Anzahl der Tage im aktuellen Monat
                $month_weight = (isset($weights[$current_month - 1]) && isset($weights[$current_month - 1]['Factor'])) ? $weights[$current_month - 1]['Factor'] : 0;
                $daily_weight = $month_weight / $days_in_month;
                $monthly_sum = 0;

                for ($day = 1; $day <= $days_in_month; $day++) {
                    $daily_sum = $actual_day_kwh * $daily_weight;
                    $monthly_sum += $daily_sum;
                }

                $monthly_forecast[] = [
                    'month'       => $current_month,
                    'year'        => $current_year,
                    'consumption' => $monthly_sum
                ];

                $calculated_forecast += $monthly_sum * $month_weight;
            }
        }

        $monthly_forecast_json = json_encode($monthly_forecast);

        return [
            'kwh_day_difference'  => $actual_day_kwh * 365,
            'calculated_forecast' => $calculated_forecast,
            'monthly_forecast'    => $monthly_forecast,
        ];
    }
}