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
    private function calculatePeriod($value, $period)
    {
        // Berechnung Schaltjahr
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
    }

    // Kosten seit Abrechnung
    private function CostsSinceInvoice($basePrice, $invoiceDate, $calorificValue, $currentConsumption, $kwhPrice)
    {
        $date = json_decode($invoiceDate, true);
        $timestamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $days_since = floor((time() - $timestamp) / (60 * 60 * 24));
        $baseCosts = ($basePrice * $days_since);
        $kwh = $currentConsumption * $calorificValue;
        $kwhCosts = $kwh * $kwhPrice;
        $costs = $kwhCosts + $baseCosts;
        $this->SetValue('GCM_CostsSinceInvoice', $costs);
        return $costs;
    }

    // Berechnung Differenz zwischen m3 Rechnungsstellung und Aktuell
    private function DifferenceFromInvoice($actualCounterValue, $invoiceCount)
    {
        $result = ($actual - $invoice);
        $this->SetValue('GCM_CurrentConsumption', $result);
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