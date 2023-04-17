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
    private function calculateForecastCosts($invoice_date, $base_price, $kwh_forecast, $kwh_price)
    {
        $date_arr = json_decode($invoice_date, true);
        $invoice_dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $date_arr['year'], $date_arr['month'], $date_arr['day']));
        $future_dt = $invoice_dt->modify('+1 year');
        $days_total = $future_dt->diff($invoice_dt)->days;
        $base_costs = $base_price * $days_total;
        $kwh_costs = $kwh_forecast * $kwh_price;
        $costs_forecast = $base_costs + $kwh_costs;
        $this->SendDebug('CalculationsHelper: kwh_forecast', $kwh_forecast, 0);
        return $costs_forecast;
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
        $days_since = floor((time() - $time_stamp) / (60 * 60 * 24));

        $actual_day_kwh = 0;
        if ($actual_kwh != 0 && $days_since != 0) {
            $actual_day_kwh = $actual_kwh / $days_since;
        }

        $weights = json_decode($month_factor, true);
        $last_month = intval($date['month']);
        $last_year = intval($date['year']);
        $months_since_invoice = 12;

        $calculated_forecast = 0;

        for ($i = 0; $i < $months_since_invoice; $i++) {
            $current_month = ($last_month + $i - 1) % 12 + 1;
            $current_year = $last_year + (int) (($last_month + $i - 1) / 12);

            $days_in_month = date('t', mktime(0, 0, 0, $current_month, 1, $current_year));
            $month_weight = (isset($weights[$current_month - 1]) && isset($weights[$current_month - 1]['Factor'])) ? $weights[$current_month - 1]['Factor'] : 0;

            $monthly_sum = $actual_day_kwh * $days_in_month * $month_weight;
            $calculated_forecast += $monthly_sum;
        }

        return $calculated_forecast;
    }
}