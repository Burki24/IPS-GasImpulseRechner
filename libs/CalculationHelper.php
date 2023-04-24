<?php

declare(strict_types=1);

trait CalculationHelper
{
    // Umrechnung m3 in kwh
    private function calculateKWH(float $calorific_value, float $cubic_meter, float $condition_number): float
    {
        $kwh = $calorific_value * $cubic_meter * $condition_number;
        $this->SetValue('GCM_UsedKWH', $kwh);
        $this->SendDebug('CalculateKWH', $kwh, 0);
        return $kwh;
    }

    // Grundpreisperiode berechnen
    private function calculatePeriod(float $base_price, string $period, int $billing_months, string $invoice_date): float
    {
        $days_in_year = (int) date('L') ? 366 : 365;
        if ($billing_months != 12) {
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
        return $base_price / $daysInPeriod;
    }

    // Kosten seit Abrechnung
    private function calculateCosts(float $base_price, string $invoice_date, float $current_kwh_consumption, float $kwh_price): float
    {
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $time_now = time();

        $dateTime = (new DateTimeImmutable())->setTimestamp($time_stamp);
        $time_stampPlusOneYear = $dateTime->add(new DateInterval('P1Y'))->getTimestamp();
        $seconds_in_day = 60 * 60 * 24;

        $days_since = floor(($time_now - $time_stamp) / $seconds_in_day);
        $days_until = abs(floor(($time_now - $time_stampPlusOneYear) / $seconds_in_day));

        $baseCosts = round($base_price * $days_since, 2);
        $kwh = round($current_kwh_consumption, 2);
        $kwhCosts = round($kwh * $kwh_price, 2);
        $costs = round($kwhCosts + $baseCosts, 2);

        if ($days_since > 0) {
            $days_total = $days_since + $days_until;
            $costs_forecast = ($days_total * $base_price) + (($costs / $days_since) * $days_total);
            $this->SetValue('GCM_CostsSinceInvoice', $costs);
        }
        return $costs;
    }

    // Zu erwartende Kosten
    private function calculateForecastCosts(string $invoice_date, float $base_price, float $kwh_forecast, float $kwh_price): array
    {
        $date_arr = json_decode($invoice_date, true);
        $invoice_dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $date_arr['year'], $date_arr['month'], $date_arr['day']));
        $future_dt = $invoice_dt->modify('+1 year');
        $days_total = $future_dt->diff($invoice_dt)->days;
        $days_remaining = $future_dt->diff(new DateTimeImmutable())->days;
        $days_passed = $days_total - $days_remaining;
        $base_costs = $base_price * $days_total;
        $kwh_costs = $kwh_forecast * $kwh_price;
        $forecast_costs = $base_costs + $kwh_costs;
        return [
            'days_remaining' => (float) $days_remaining,
            'days_passed'    => (float) $days_passed,
            'forecast_costs' => (float) $forecast_costs,
        ];
    }

    // Berechnung Differenz zwischen m3 Rechnungsstellung und Aktuell
    private function DifferenceFromInvoice(float $actual_counter_value, float $install_counter_value, float $calorific_value, float $condition_number): float

    {
        $result = ($actual_counter_value - $install_counter_value);
        $kwh = ($result * $calorific_value * $condition_number);
        $this->SetValue('GCM_CurrentConsumption', $result);
        $this->SetValue('GCM_KWHSinceInvoice', $kwh);
        $this->SendDebug('Calculation -> install_counter_value', $install_counter_value, 0);
        $this->SendDebug('Calculation -> Calorific_Value', $calorific_value, 0);
        $this->SendDebug('Calculation -> condition_number', $condition_number, 0);


        return $result;
    }

    // Kosten aktueller Tag
    private function CalculateCostActualDay(float $base_price, float $calorific_value, float $kwh_day, float $kwh_price, float $condition_number): float
    {
        $kwhCosts = $kwh_day * $kwh_price * $condition_number;
        $costs = $kwhCosts + $base_price;
        $this->SetValue('GCM_DayCosts', $costs);
        return $costs;
    }

    // Aktuelles Datum berechnen
    private function GetCurrentDate(): string
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
    private function LumpSumYear(int $billing_months, float $lump_sum, float $old_lump_sum, string $invoice_date): float
    {
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $date_string = date('Y-m-d', $time_stamp);
        $current_date = date('Y-m-d');

        $diff = date_diff(date_create($date_string), date_create($current_date));
        $months_since_invoice = $diff->m + ($diff->d >= 1 ? 1 : 0);

        if ($old_lump_sum == 0) {
            $result = $billing_months * $lump_sum;
        } else {
            $old_lump_sum_result = $months_since_invoice * $old_lump_sum;
            $remaining_months = $billing_months - $months_since_invoice;
            $new_lump_sum_result = $remaining_months * $lump_sum;
            $result = $old_lump_sum_result + $new_lump_sum_result;
        }

        return $result;
    }

    // Differenz zu erwartende Kosten
    private function LumpSumDifference(float $lump_sum_year, float $costs_forecast): float
    {
        $difference = ($lump_sum_year - $costs_forecast);
        return $difference;
    }

    // Bisher gezahlte Abschläge
    private function LumpSumPast(float $lump_sum, string $invoice_date, int $billing_months): float
    {
        $days_in_year = (int) date('L') ? 366 : 365;
        if ($billing_months != 12) {
            $days_in_year = (int) date('L', strtotime('+2 months')) ? 396 : 395;
        }
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $months_since = ((date('Y') - $date['year']) * 12) + (date('m') - $date['month']);
        $result = $lump_sum * $months_since;
        return $result;
    }

    // KWH Forecast
    private function ForecastKWH(float $invoice_kwh, string $invoice_date, float $actual_kwh, string $month_factor): float
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
