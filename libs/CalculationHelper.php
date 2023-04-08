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
    private function CalculateCostActualDay($base_price, $calorific_value, $kwh_day, $kwh_price)
    {
        $kwhCosts = $kwh_day * $kwh_price;
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
    private function InvoiceKWH($invoice_kwh, $invoice_date, $kwh)
    {
        $days_in_year = (int) date('L') ? 366 : 365;
        $date = json_decode($invoice_date, true);
        $time_stamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
        $days_since = floor((time() - $time_stamp) / (60 * 60 * 24));
        $invoice_day_kwh = $invoice_kwh / 365;
        $actual_day_kwh = $kwh / $days_since;
        $kwh_day_difference = ($actual_day_kwh * $days_in_year) - ($invoice_day_kwh * $days_in_year);
        $weights = [
            'jan' => 1.0,
            'feb' => 1.0,
            'mar' => 0.9,
            'apr' => 0.9,
            'may' => 0.8,
            'jun' => 0.8,
            'jul' => 0.7,
            'aug' => 0.7,
            'sep' => 0.8,
            'oct' => 0.9,
            'nov' => 1.0,
            'dec' => 1.0
        ];
        $total_weight = array_sum($weights); // Summe der Gewichte berechnen
        $this->SendDebug('Summe der Gewichte', $total_weight, 0);
        $this->SendDebug('Invoice_date', $invoice_date, 0);
        $current_month = intval(date('m'));
        $this->SendDebug('Monat', $current_month, 0);
        $current_year = intval(date('Y')); // Aktuelles Jahr ermitteln
        $this->SendDebug('Jahr', $current_year, 0);
        if (is_int($current_year) && is_numeric($current_month)) {
            foreach ($weights as $month => $weight) {
                $days_in_month = cal_days_in_month(CAL_GREGORIAN, intval(date('m', strtotime("1 $current_year-$month"))), $current_year);
                $monthly_sum = 0;
                $output = '';
                if ($days_in_month === false) {
                    $output .= "Ungültiges Datum: $days_in_month";
                } else {
                    // gültiges Datum
                    $output .= 'Tage im Monat: ' . $days_in_month . "\n";
                    $daily_weight = $weight / $days_in_month; // Tägliches Gewicht berechnen
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $daily_sum = $sum * $daily_weight / $total_weight; // Tägliche Summe berechnen
                        if ($current_month == $month && $day == date('j')) {
                            $monthly_sum += $daily_sum; // Aktuellen Tag zur monatlichen Summe hinzufügen
                        }
                        $output .= 'Day ' . str_pad($day, 2, '0', STR_PAD_LEFT) . ' of ' . ucfirst($month) . ': ' . round($daily_sum, 2) . "\n"; // Ausgabe formatieren
                    }
                    $output .= 'Monthly sum for ' . ucfirst($month) . ': ' . round($monthly_sum, 2);
                    if ($current_month == $month) {
                        $output .= ' (current month)';
                    }
                    $output .= "\n\n"; // Ausgabe formatieren
                }
                echo $output;
            }
        } else {
            echo 'Ungültiges Datum: current_year oder current_month';
        }
        $this->SendDebug('Monatliche Summe Vorjahr', $monthly_sum, 0);
        $this->SendDebug('Tägliche Summe Vorjahr', $daily_sum, 0);
        $this->SendDebug('Aktuelle Differenz zum Vorjahr', $kwh_day_difference, 0);
        $this->SendDebug('aktuelle tägliche kwh', $actual_day_kwh, 0);
        $this->SendDebug('Tägliche KWH letztes Jahr', $invoice_day_kwh, 0);
        $this->SendDebug('Tage seit letzter Abrechnung KWH Forecast', $days_since, 0);
        $result = $kwh_day_difference * $days_since;
        $this->SetValue('GCM_KWHDifference', $kwh_day_difference);
        return $result;
    }
}