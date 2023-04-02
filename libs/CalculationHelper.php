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
        $daysInYear = (int) date('L') ? 366 : 365;
        if ($months != 12) {
            $daysInYear = (int) date('L', strtotime('+2 months')) ? 396 : 395;
        }
        switch ($period) {
            case 'year':
                $daysInPeriod = $daysInYear;
                break;
            case 'half_year':
                $daysInPeriod = $daysInYear / 2;
                break;
            case 'quarter_year':
                $daysInPeriod = $daysInYear / 4;
                break;
            case 'month':
                $daysInPeriod = $daysInYear / 12;
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
            $costs_forecast_heating = ($days_total * $base_price) + (($costs / $days_since) * $days_total * 0.7);
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
    private function CalculateCostActualDay($base_price, $calorific_value, $kwh, $kwh_price)
    {
        $kwhCosts = $kwh * $kwh_price;
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

    private function LumpSum($months, $lump_sum, $kwh_forecast, $base_price, $costs_forecast);
}