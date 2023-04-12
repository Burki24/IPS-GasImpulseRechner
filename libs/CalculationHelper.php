<?php

declare(strict_types=1);

trait CalculationHelper
{
    // Umrechnung m3 in kwh
    private function calculateKWH($calorific_value, $cubic_meter)
    {
        $kwh = $calorific_value * $cubic_meter;
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
    private function calculations($base_price, $invoice_date, $calorific_value, $current_consumption, $kwh_price)
    {
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $time_stampPlusOneYear = (new DateTimeImmutable())->setTimestamp($time_stamp)->add(new DateInterval('P1Y'))->getTimestamp();
        $days_since = floor((time() - $time_stamp) / (60 * 60 * 24));
        $daysUntil = abs(floor((time() - $time_stampPlusOneYear) / (60 * 60 * 24)));
        $baseCosts = round($base_price * $days_since, 2);
        $kwh = round($current_consumption * $calorific_value, 2);
        $kwhCosts = round($kwh * $kwh_price, 2);
        $costs = round($kwhCosts + $baseCosts, 2);

        if ($days_since > 0) {
            $days_total = $days_since + $daysUntil;
            $costs_forecast = ($days_total * $base_price) + (($costs / $days_since) * $days_total);
            $kwh_forecast = (($kwh / $days_since) * $days_total);
            $this->SetValue('GCM_CostsSinceInvoice', $costs);
            $this->SetValue('GCM_DaysSinceInvoice', $days_since);
            $this->SetValue('GCM_DaysTillInvoice', $daysUntil);
            $this->SetValue('GCM_CostsForecast', $costs_forecast);
            $this->SetValue('GCM_kwhForecast', $kwh_forecast);
        }
        return $costs;
    }

    // Berechnung Differenz zwischen m3 Rechnungsstellung und Aktuell
    private function DifferenceFromInvoice($actual_counter_value, $invoice_count, $calorific_value)
    {
        $result = ($actual_counter_value - $invoice_count);
        $kwh = ($result * $calorific_value);
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
        $old_lump_sum_result = $months_since_invoice * $old_lump_sum;
        $remaining_months = $months - $months_since_invoice;
        $new_lump_sum_result = $remaining_months * $lump_sum;
        $result = $old_lump_sum_result + $new_lump_sum_result;
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
        $date = json_decode($invoice_date, true); // Rechnungsdatum
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']); // Datum formatieren
        $days_since = floor((time() - $time_stamp) / (60 * 60 * 24)); // Tage seit Abrechnung
        if ($actual_kwh != 0 && $days_since != 0) {
            $actual_day_kwh = $actual_kwh / $days_since; // Aktueller Verbrauch auf Tage seit Abrechnung gebrochen
            $kwh_day_difference = $actual_day_kwh * 365; // Jahresverbrauch basierend auf aktuellem Verbrauch
        }
        $weights = json_decode($month_factor, true);
        $total_weight = array_sum(array_column($weights, 'Factor')); // Summe der Gewichte berechnen
        $last_month = intval($date['month']); // Letzter Monat aus $invoice_date
        $last_year = intval($date['year']); // Letztes Jahr aus $invoice_date
        $today = getdate(); // Heutiges Datum
        $current_month = intval($today['mon']); // Aktueller Monat
        $current_year = intval($today['year']); // Aktuelles Jahr
        $months_since_invoice = ($current_year - $last_year) * 12 + $current_month - $last_month;
        $monthly_forecast = [];
        for ($i = 0; $i <= $months_since_invoice; $i++) {
            $current_month = ($last_month + $i - 1) % 12 + 1;
            $current_year = $last_year + (int) (($last_month + $i - 1) / 12);
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
            $month_weight = (isset($weights[$current_month - 1]) && isset($weights[$current_month - 1]['Factor'])) ? $weights[$current_month - 1]['Factor'] : 0;

            $daily_weight = $month_weight / $days_in_month; // Tägliches Gewicht berechnen
            $monthly_sum = 0;
            $kwh_day_difference = 0;
            $calculated_forecast = 0;
            $monthly_forecast = [];

            for ($day = 1; $day <= $days_in_month; $day++) {
                $daily_sum = $kwh_day_difference * $daily_weight / $total_weight; // Tägliche Summe berechnen
                $monthly_sum += $daily_sum;
            }

            $monthly_forecast[] = [
                'month'       => $current_month,
                'year'        => $current_year,
                'consumption' => $monthly_sum
            ];
            $this->SendDebug('weights', json_encode($weights), 0);
            $calculated_forecast = 0;
        }
        for ($i = 0; $i < 12; $i++) {
            if (isset($monthly_forecast[$i])) {
                $calculated_forecast += $monthly_forecast[$i]['consumption'];
            }
        }
        return [
            'kwh_day_difference' => $kwh_day_difference,
            'calculated_forecast' => $calculated_forecast,
            'monthly_forecast' => $monthly_forecast,
        ];
    }
}