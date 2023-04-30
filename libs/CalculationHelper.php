<?php

declare(strict_types=1);

trait CalculationHelper
{
    /**
     * Calculate KWH.
     *
     * @param float $calorific_value
     * @param float $cubic_meter
     * @param float $condition_number
     * @return float
     */
    private function calculateKWH(float $calorific_value, float $cubic_meter, float $condition_number): float
    {
        $kwh = $calorific_value * $cubic_meter * $condition_number;
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
    private function calculateCosts(float $baseprice_day, string $invoice_date, float $current_kwh_consumption, float $kwh_price): float
    {
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $time_now = time();

        $dateTime = (new DateTimeImmutable())->setTimestamp($time_stamp);
        $time_stampPlusOneYear = $dateTime->add(new DateInterval('P1Y'))->getTimestamp();
        $seconds_in_day = 60 * 60 * 24;

        $days_since = floor(($time_now - $time_stamp) / $seconds_in_day);
        $days_until = abs(floor(($time_now - $time_stampPlusOneYear) / $seconds_in_day));

        $baseCosts = round($baseprice_day * $days_since, 2);
        $kwh = round($current_kwh_consumption, 2);
        $kwhCosts = round($kwh * $kwh_price, 2);
        $costs = round($kwhCosts + $baseCosts, 2);

        if ($days_since > 0) {
            $days_total = $days_since + $days_until;
            $costs_forecast = ($days_total * $baseprice_day) + (($costs / $days_since) * $days_total);
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

    // Berechnung Differenz zwischen m3 & kw/h Rechnungsstellung und Aktuell
    private function DifferenceFromInvoice(float $actual_counter_value, float $invoice_count, float $calorific_value, float $condition_number): array
    {
        $result = ($actual_counter_value - $invoice_count);
        $kwh = ($result * $calorific_value * $condition_number);
        return [
            'result'    => (float) $result,
            'kwh'       => (float) $kwh
        ];
    }

    // Kosten aktueller Tag
    private function CalculateCostActualDay(float $baseprice_day, float $calorific_value, float $kwh_day, float $kwh_price, float $condition_number): float
    {
        $kwhCosts = $kwh_day * $kwh_price * $condition_number;
        $costs = $kwhCosts + $baseprice_day;
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
    /**
     * Calculate KWH forecast.
     *
     * @param float $invoice_kwh
     * @param string $invoice_date
     * @param float $actual_kwh
     * @param string $month_factor
     * @return float
     */
    private function ForecastKWH(float $invoice_kwh, string $invoice_date, float $actual_kwh, string $month_factor): float
    {
        $date = json_decode($invoice_date, true);
        $invoiceDateTime = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $date['year'], $date['month'], $date['day']));
        $now = new DateTimeImmutable();

        $days_since = $now->diff($invoiceDateTime)->days;
        $actual_day_kwh = $actual_kwh === 0 || $days_since === 0 ? 0 : $actual_kwh / $days_since;

        $weights = json_decode($month_factor, true);
        $calculated_forecast = 0;

        for ($i = 0; $i < 12; $i++) {
            $currentMonthDateTime = $invoiceDateTime->add(new DateInterval("P{$i}M"));
            $days_in_month = (int) $currentMonthDateTime->format('t');
            $current_month = (int) $currentMonthDateTime->format('n');

            $month_weight = $weights[$current_month - 1]['Factor'] ?? 0;
            $monthly_sum = $actual_day_kwh * $days_in_month * $month_weight;

            $calculated_forecast += $monthly_sum;
        }
        $this->SendDebug('Calculations -> ForecastKWH -> $actual_kwh', $actual_kwh, 0);
        return $calculated_forecast;
    }
}
