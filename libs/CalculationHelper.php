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
        // Berechnung Schaltjahr
        if ($months == 12) {
            $daysInYear = 365;
            if (checkdate(2, 29, (int) date('Y'))) {
                $daysInYear = 366;
            }

            switch ($period) {
                // Jahreszahlung
               case 'year':
                    $result = $value / $daysInYear;
                    break;
                // Halbjahreszahlung
                case 'half_year':
                    $daysInPeriod = $daysInYear / 2;
                    $result = $value / $daysInPeriod;
                    break;
                // Viertljährliche Zahlung
                case 'quarter_year':
                    $daysInPeriod = $daysInYear / 4;
                    $result = $value / $daysInPeriod;
                    break;
                // Monatliche Zahlung
                case 'month':
                    $daysInPeriod = $daysInYear / 12;
                    $result = $value / $daysInPeriod;
                    break;
                // Tägliche Zahlung
                case 'day':
                    $result = $value / 1;
                    break;
                // Falsche Zeitraumangabe
                default:
                    throw new InvalidArgumentException('Invalid period provided.');
            }
            return $result;
        } else {
            $daysInYear = 395;
            if (checkdate(2, 29, (int) date('Y'))) {
                $daysInYear = 396;
            }

            switch ($period) {
                // Jahreszahlung
               case 'year':
                    $result = $value / $daysInYear;
                    break;
                // Halbjahreszahlung
                case 'half_year':
                    $daysInPeriod = $daysInYear / 2;
                    $result = $value / $daysInPeriod;
                    break;
                // Viertljährliche Zahlung
                case 'quarter_year':
                    $daysInPeriod = $daysInYear / 4;
                    $result = $value / $daysInPeriod;
                    break;
                // Monatliche Zahlung
                case 'month':
                    $daysInPeriod = $daysInYear / 12;
                    $result = $value / $daysInPeriod;
                    break;
                // Tägliche Zahlung
                case 'day':
                    $result = $value / 1;
                    break;
                // Falsche Zeitraumangabe
                default:
                    throw new InvalidArgumentException('Invalid period provided.');
            }
            return $result;
        }
    }

    // Kosten seit Abrechnung
    private function calculations($basePrice, $invoiceDate, $calorificValue, $currentConsumption, $kwhPrice)
    {
        $date = json_decode($invoiceDate, true);
        $timestamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $timestampPlusOneYear = strtotime('+1 year', $timestamp);
        $days_since = floor((time() - $timestamp) / (60 * 60 * 24));
        $daysUntil = abs(floor((time() - $timestampPlusOneYear) / (60 * 60 * 24)));
        $baseCosts = round($basePrice * $days_since, 2);
        $kwh = round($currentConsumption * $calorificValue, 2);
        $kwhCosts = round($kwh * $kwhPrice, 2);
        $costs = round($kwhCosts + $baseCosts, 2);
        if ($days_since > 0) {
            $costs_forecast = (($daysUntil + $days_since) * $basePrice) + (($costs / $days_since) * ($days_since + $daysUntil));
            $costs_forecast_heating = (($daysUntil + $days_since) * $basePrice) + (($costs / $days_since) * (($days_since + $daysUntil) * 0.7));
            $kwh_forecast = (($kwh / $days_since) * ($days_since + $daysUntil));
        }
        $this->SendDebug('Zeitstempel', $timestamp, 0);
        $this->SendDebug('Zeitstempel plus ein Jahr', $timestampPlusOneYear, 0);
        $this->SendDebug('costs ohne Heizung', $costs_forecast, 0);
        $this->SendDebug('costs mit Heizung', $costs_forecast_heating, 0);
        $this->SendDebug('Arbeitspreis seit Rechnung', $baseCosts, 0);
        $this->SendDebug('kwh kosten seit Rechnung', $kwhCosts, 0);
        $this->SendDebug('Tage bis Rechnung', $daysUntil, 0);
        $this->SendDebug('Tage seit Rechnung', $days_since, 0);

        $this->SetValue('GCM_CostsSinceInvoice', $costs);
        $this->SetValue('GCM_DaysSinceInvoice', $days_since);
        $this->SetValue('GCM_DaysTillInvoice', $daysUntil);
        $this->SetValue('GCM_CostsForecast', $costs_forecast);
        $this->SetValue('GCM_kwhForecast', $kwh_forecast);
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