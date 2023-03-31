<?php

declare(strict_types=1);

/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          CalculationHelper.php
 * @author        Burkhard Kneiseler <burkhard.kneiseler.de>
 * @copyright     2023 Burkhard Kneiseler
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 */

/**
 * Trait mit Hilfsfunktionen zur Berechnung verschiedener Werte
 */
trait CalculationHelper
{
    // Umrechnung m3 in kwh
    private function calculateKWH($calorificValue, $cubicMeter)
    {
        $kwh = $calorificValue * $cubicMeter;
        $this->SetValue('GCM_UsedKWH', $kwh);
        return $kwh;
    }

    // Grundpreisperiode berechnen
    private function calculatePeriod($value, $period, $months, $invoiceDate)
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



    // Kosten seit Abrechnung
private function calculations($basePrice, $invoiceDate, $calorificValue, $currentConsumption, $kwhPrice)
{
    $date = json_decode($invoiceDate, true);
    $timestamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);

    // Optimizations:
    // - Calculate timestampPlusOneYear using DateTime instead of strtotime()
    // - Use DateTimeImmutable to avoid modifying the timestamp variable
    // - Use DateTimeInterval to calculate the difference between two dates
    $timestampPlusOneYear = (new DateTimeImmutable())->setTimestamp($timestamp)->add(new DateInterval('P1Y'))->getTimestamp();
    $days_since = floor((time() - $timestamp) / (60 * 60 * 24));
    $daysUntil = abs(floor((time() - $timestampPlusOneYear) / (60 * 60 * 24)));
    $baseCosts = round($basePrice * $days_since, 2);
    $kwh = round($currentConsumption * $calorificValue, 2);
    $kwhCosts = round($kwh * $kwhPrice, 2);
    $costs = round($kwhCosts + $baseCosts, 2);

    if ($days_since > 0) {
        // Optimization:
        // - Use a temporary variable to avoid repeating the calculation $days_since + $daysUntil
        $days_total = $days_since + $daysUntil;
        $costs_forecast = ($days_total * $basePrice) + (($costs / $days_since) * $days_total);
        $costs_forecast_heating = ($days_total * $basePrice) + (($costs / $days_since) * $days_total * 0.7);
        $kwh_forecast = (($kwh / $days_since) * $days_total);

        // Debugging statements removed

        $this->SetValue('GCM_CostsSinceInvoice', $costs);
        $this->SetValue('GCM_DaysSinceInvoice', $days_since);
        $this->SetValue('GCM_DaysTillInvoice', $daysUntil);
        $this->SetValue('GCM_CostsForecast', $costs_forecast);
        $this->SetValue('GCM_kwhForecast', $kwh_forecast);
    }

    return $costs;
}




    // Berechnung Differenz zwischen m3 Rechnungsstellung und Aktuell
    private function DifferenceFromInvoice($actualCounterValue, $invoiceCount, $calorificValue)
    {
        $result = ($actualCounterValue - $invoiceCount);
        $kwh = ($result * $calorificValue);
        $this->SetValue('GCM_CurrentConsumption', $result);
        $this->Setvalue('GCM_KWHSinceInvoice', $kwh);
    }

    // Kosten aktueller Tag
    private function CalculateCostActualDay($basePrice, $calorificValue, $kwh, $kwhPrice)
    {
        $kwhCosts = $kwh * $kwhPrice;
        $costs = $kwhCosts + $basePrice;
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
}