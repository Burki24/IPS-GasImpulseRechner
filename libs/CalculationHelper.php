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
        $this->SendDebug('cubicmeter', $cubicMeter, 0);
        $this->SendDebug('kwh calculate', $kwh, 0);
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
}