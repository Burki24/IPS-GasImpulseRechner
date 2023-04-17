<?php

    declare(strict_types=1);

    require_once __DIR__ . '/../libs/CalculationHelper.php';
    // require_once __DIR__ . '/../libs/SymconModulHelper/DebugHelper.php';

    class GasImpulsVerbrauchsanalyse extends IPSModule
    {
        use CalculationHelper;

        public function Create()
        {
            //Never delete this line!
            parent::Create();

            //Werte aus dem Formular
            $this->RegisterPropertyInteger('ImpulseID', 0);
            $this->RegisterPropertyFloat('ImpulseValue', 0.00);
            $this->RegisterPropertyFloat('BasePrice', 0);
            $this->RegisterPropertyString('BasePricePeriod', 'month');
            $this->RegisterPropertyFloat('CalorificValue', 0);
            $this->RegisterPropertyFloat('InvoiceCounterValue', 0);
            $this->RegisterPropertyString('InvoiceDate', $this->GetCurrentDate());
            $this->RegisterPropertyFloat('InstallCounterValue', 0);
            $this->RegisterPropertyFloat('KWHPrice', 0);
            $this->RegisterPropertyInteger('BillingMonths', 11);
            $this->RegisterPropertyFloat('LumpSum', 0);
            $this->RegisterPropertyInteger('InvoiceKWH', 0);
            $this->RegisterPropertyString('MonthFactor', '[{"Name":"January","Factor":"1.0"},{"Name":"February","Factor":0.9},{"Name":"March","Factor":0.85},{"Name":"April","Factor":0.70},{"Name":"May","Factor":0.6},{"Name":"June","Factor":0.45},{"Name":"July","Factor":0.35},{"Name":"August","Factor":0.35},{"Name":"September","Factor":0.55},{"Name":"October","Factor":0.8},{"Name":"November","Factor":0.9},{"Name":"December","Factor":"1.0"}]');
            $this->RegisterPropertyFloat('ConditionNumber', 0);

            // Zur Berechnung bereitzustellende Werte
            $this->RegisterAttributeFloat('Attrib_InstallCounterValueOld', 0);
            $this->RegisterAttributeFloat('Attrib_ActualCounterValue', 0);
            $this->RegisterAttributeFloat('Attrib_DayCount', 0);
            $this->RegisterAttributeFloat('Attrib_LumpSumPast', 0);

            // Profil erstellen
            if (!IPS_VariableProfileExists('GCM.Gas.kWh')) {
                IPS_CreateVariableProfile('GCM.Gas.kWh', VARIABLETYPE_FLOAT);
                IPS_SetVariableProfileDigits('GCM.Gas.kWh', 2);
                IPS_SetVariableProfileText('GCM.Gas.kWh', '', ' kW/h');
                IPS_SetVariableProfileIcon('GCM.Gas.kWh', 'Flame');
            }
            if (!IPS_VariableProfileExists('GCM.Days')) {
                IPS_CreateVariableProfile('GCM.Days', VARIABLETYPE_INTEGER);
                IPS_SetVariableProfileText('GCM.Days', '', ' ' . $this->Translate('Days'));
                IPS_SetVariableProfileIcon('GCM.Days', 'Calendar');
            }

            // Variablen erstellen
            // Zur Berechnung
            $this->RegisterVariableFloat('GCM_CounterValue', $this->Translate('Current Meter Reading'), '~Gas');
            $this->RegisterVariableFloat('GCM_BasePrice', $this->Translate('Base Price'), '~Euro');

            // Aktueller Tag
            $this->RegisterVariableFloat('GCM_UsedKWH', $this->Translate('Daily Cosnumption kW/h'), 'GCM.Gas.kWh');
            $this->RegisterVariableFloat('GCM_UsedM3', $this->Translate('Daily Cosnumption m3'), '~Gas');
            $this->RegisterVariableFloat('GCM_DayCosts', $this->Translate('Costs Today'), '~Euro');

            // Gestriger Tag
            $this->RegisterVariableFloat('GCM_CostsYesterday', $this->Translate('Total Cost Last Day'), '~Euro');
            $this->RegisterVariableFloat('GCM_ConsumptionYesterdayKWH', $this->Translate('Total Consumption Last Day kW/h'), 'GCM.Gas.kWh');
            $this->RegisterVariableFloat('GCM_ConsumptionYesterdayM3', $this->Translate('Total Consumption Last Day m3'), '~Gas');

            // Seit Rechnungsstellung
            $this->RegisterVariableFloat('GCM_InvoiceCounterValue', $this->Translate('Meter Reading On Last Invoice'), '~Gas');
            $this->RegisterVariableFloat('GCM_CurrentConsumption', $this->Translate('Total Consumption Actually in m3'), '~Gas');
            $this->RegisterVariableFloat('GCM_CostsSinceInvoice', $this->Translate('Costs Since Invoice'), '~Euro');
            $this->RegisterVariableFloat('GCM_KWHSinceInvoice', $this->Translate('kW/h since Invoice'), 'GCM.Gas.kWh');
            $this->RegisterVariableInteger('GCM_DaysSinceInvoice', $this->Translate('Days since Invoice'), 'GCM.Days');

            // Forecast
            $this->RegisterVariableInteger('GCM_DaysTillInvoice', $this->Translate('Days remaining in billing period'), 'GCM.Days');
            $this->RegisterVariableFloat('GCM_CostsForecast', $this->Translate('assumed amount of the next bill'), '~Euro');
            $this->RegisterVariableFloat('GCM_kwhForecast', $this->Translate('assumed consumption level in kWh'), 'GCM.Gas.kWh');
            // $this->RegisterVariableFloat('GCM_KWHDifference', $this->Translate('kwh difference'), 'GCM.Gas.kWh');

            // Kalkulation Abschlagszahlungen vs. Real-Verbrauch
            $this->RegisterVariableFloat('GCM_LumpSumYear', $this->Translate('Lump Sum Year'), '~Euro');
            $this->RegisterVariableFloat('GCM_LumpSumDiff', $this->Translate('Lump Sum Difference'), '~Euro');

            // Messages
            $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        }
        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }
        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();
            // Benötigte Variablen
            $month_factor = $this->ReadPropertyString('MonthFactor');
            $old_lump_sum = $this->ReadAttributeFloat('Attrib_LumpSumPast');
            $lump_sum = $this->ReadPropertyFloat('LumpSum');
            $months = $this->ReadPropertyInteger('BillingMonths');
            $invoice_date = $this->ReadPropertyString('InvoiceDate');
            $install_value = $this->ReadPropertyFloat('InstallCounterValue');
            $actual_value = $this->GetValue('GCM_CounterValue');
            $actual_counter_value = $this->GetValue('GCM_CounterValue');
            $invoice_count = $this->ReadPropertyFloat('InvoiceCounterValue');
            $calorific_value = $this->ReadPropertyFloat('CalorificValue');
            $old_counter_value = $this->ReadAttributeFloat('Attrib_InstallCounterValueOld');
            $new_counter_value = $this->ReadPropertyFloat('InstallCounterValue');
            $value = $this->ReadPropertyFloat('BasePrice');
            $period = $this->ReadPropertyString('BasePricePeriod');
            $condition_number = $this->ReadPropertyFloat('ConditionNumber');

            // Eintragung des kalkulierten Grundpreises
            if (IPS_VariableExists($this->GetIDForIdent('GCM_BasePrice'))) {
                $result = $this->calculatePeriod($value, $period, $months, $invoice_date);
                $this->SetValue('GCM_BasePrice', $result);
            }

            // Eintragung der Jahresabschlagshöhe
            if (IPS_VariableExists($this->GetIDForIdent('GCM_LumpSumYear'))) {
                $this->WriteAttributeFloat('Attrib_LumpSumPast', $lump_sum);
                $result = $this->LumpSumYear($months, $lump_sum, $old_lump_sum, $invoice_date);
                $this->SetValue('GCM_LumpSumYear', $result);
            }

            // Eintragung Zählerstand bei Rechnungsstellung
            if (IPS_VariableExists($this->GetIDForIdent('GCM_InvoiceCounterValue'))) {
                $Value = $this->ReadPropertyFloat('InvoiceCounterValue');
                $this->SetValue('GCM_InvoiceCounterValue', $Value);
            }

            // Eintragung Zählerstand bei Installation
            if (IPS_VariableExists($this->GetIDForIdent('GCM_CounterValue'))) {
                if ($actual_value < $install_value) {
                    $this->SetValue('GCM_CounterValue', $install_value);
                }
            }

            // Errechnung Zählerstanddifferenz bei Installation
            if (IPS_VariableExists($this->GetIDForIdent('GCM_CurrentConsumption'))) {
                $this->DifferenceFromInvoice($actual_counter_value, $invoice_count, $calorific_value, $condition_number);
            }

            //  ImpulseCounter zurücksetzen
            if ($old_counter_value !== $new_counter_value) {
                $this->ImpulseCounterReset();
            }

            // Event Tagesende starten
            $eid = @$this->GetIDForIdent('GCM_EndOfDayTimer');
            if ($eid == 0) {
                $eid = IPS_CreateEvent(1);
                IPS_SetParent($eid, $this->InstanceID);
                IPS_SetIdent($eid, 'GCM_EndOfDayTimer');
                IPS_SetName($eid, $this->Translate('End Of Day Timer'));
                IPS_SetEventActive($eid, true);
                IPS_SetEventCyclic($eid, 0 /* Täglich */, 1 /* Jeder Tag */, 0 /* Egal welcher Wochentag */, 0 /* Egal welcher Tag im Monat */, 0, 0);
                IPS_SetEventCyclicTimeFrom($eid, 23, 59, 50);
                IPS_SetEventCyclicTimeTo($eid, 23, 59, 59);
            } else {
                IPS_SetEventCyclic($eid, 0 /* Täglich */, 1 /* Jeder Tag */, 0 /* Egal welcher Wochentag */, 0 /* Egal welcher Tag im Monat */, 0, 0);
                IPS_SetEventCyclicTimeFrom($eid, 23, 59, 50);
                IPS_SetEventCyclicTimeTo($eid, 23, 59, 59);
            }
            IPS_SetEventScript($eid, 'GCM_DaySwitch($_IPS[\'TARGET\']);');

            // Impuls Verwertung
            $this->GasCounter();
        }

        // Messagesink - Impulseauswertung
        public function MessageSink($time_stamp, $sender_id, $Message, $Data)
        {
            switch ($Message) {
                    case VM_UPDATE:
                        // $impulse_id = $this->ReadPropertyInteger('ImpulseID');
                        // $impulse_state = GetValue($impulse_id);
                        $this->GasCounter();
                    break;
                default:
                    $this->SendDebug(__FUNCTION__ . ':: Messages from Sender ' . $sender_id, $Data, 0);
                    break;
                }
        }

        //Tagesabschluss
        public function DaySwitch()
        {
            // Speichern der gestrigen Verbrauchswerte
            $this->SetValue('GCM_ConsumptionYesterdayM3', $this->GetValue('GCM_UsedM3'));
            $this->SetValue('GCM_ConsumptionYesterdayKWH', $this->GetValue('GCM_UsedKWH'));
            $this->SetValue('GCM_CostsYesterday', $this->GetValue('GCM_DayCosts'));
            // Zurücksetzen der Tageswerte
            $this->SetValue('GCM_UsedM3', 0);
            $this->SetValue('GCM_UsedKWH', 0);
            $this->SetValue('GCM_DayCosts', 0);
            // Tage seit Rechnung aktualisieren
        }
        // Eintrag neuer InstallCounterwert
        private function updateInstallCounterValue()
        {
            $install_counter_value = $this->ReadpropertyFloat('InstallCounterValue');
            static $install_counter_value_old;
            if ($install_counter_value != $install_counter_value_old) {
                $install_counter_value_old = $install_counter_value;
            }
        }

        // Counterreset wenn InstallCounter geändert
        private function ImpulseCounterReset()
        {
            $old_counter_value = $this->ReadAttributeFloat('Attrib_InstallCounterValueOld');
            $new_counter_value = $this->ReadPropertyFloat('InstallCounterValue');
            $this->WriteAttributeFloat('Attrib_DayCount', $this->GetValue('GCM_UsedM3'));
            $this->WriteAttributeFloat('Attrib_InstallCounterValueOld', $new_counter_value);
            $this->WriteAttributeFloat('Attrib_ActualCounterValue', 0);
            $this->SetValue('GCM_CounterValue', $new_counter_value);
            $this->SetValue('GCM_UsedM3', $this->ReadAttributeFloat('Attrib_DayCount'));
        }

        // Hauptfunktion des Moduls
        private function GasCounter()
        {
            // Registrieren der Änderungsbenachrichtigung für den Impuls
            $this->RegisterMessage($this->ReadPropertyInteger('ImpulseID'), VM_UPDATE);

            // Lesen der benötigten Variablen
            $actual_counter_value = $this->GetValue('GCM_CounterValue');
            $actual_kwh = $this->GetValue('GCM_KWHSinceInvoice');
            $base_price = $this->GetValue('GCM_BasePrice');
            $calculated_forecast = 0;
            $calorific_value = $this->ReadpropertyFloat('CalorificValue');
            $condition_number = $this->ReadPropertyFloat('ConditionNumber');
            $costs_forecast = $this->GetValue('GCM_CostsForecast');
            $cubic_meter = $this->GetValue('GCM_UsedM3');
            $current_counter_value = $this->GetValue('GCM_CounterValue');
            $current_kwh_consumption = $this->GetValue('GCM_KWHSinceInvoice');
            $impulse_id = $this->ReadPropertyInteger('ImpulseID');
            $impulse_value = $this->ReadPropertyFloat('ImpulseValue');
            $install_counter_value = $this->ReadPropertyFloat('InstallCounterValue');
            $invoice_count = $this->ReadPropertyFloat('InvoiceCounterValue');
            $invoice_date = $this->ReadpropertyString('InvoiceDate');
            $invoice_kwh = $this->ReadPropertyInteger('InvoiceKWH');
            $kwh_day = $this->GetValue('GCM_UsedKWH');
            $kwh_day_difference = 0;
            $kwh_forecast = $this->GetValue('GCM_kwhForecast');
            $kwh_price = $this->ReadpropertyFloat('KWHPrice');
            $lump_sum = $this->ReadPropertyFloat('LumpSum');
            $lump_sum_year = $this->GetValue('GCM_LumpSumYear');
            $month_factor = $this->ReadPropertyString('MonthFactor');
            $months = $this->ReadPropertyInteger('BillingMonths');

            // Aktualisierung bei Anpassung Zählerstand bei Installation
            $this->updateInstallCounterValue();
            $install_counter_value = $this->ReadpropertyFloat('InstallCounterValue');

            // Überprüfen, ob Impuls-Variable vergeben wurde und ob Impuls ausgelöst wurde
            if ($impulse_id > 0) {
                $impulse = GetValue($impulse_id);
                $impulse_used = $this->ReadAttributeBoolean('ImpulseUsed');
                if ($impulse && !$impulse_used) {
                    $new_counter_value = $current_counter_value + $impulse_value;
                    $new_cubic_meter = $cubic_meter + $impulse_value;
                    $this->WriteAttributeBoolean('ImpulseUsed', true);
                } else {
                    $new_counter_value = $current_counter_value;
                    $new_cubic_meter = $cubic_meter;
                }

                // Berechnungen durchführen
                $this->calculateCosts($base_price, $invoice_date, $current_kwh_consumption, $kwh_price);
                $this->calculateForecastCosts($invoice_date, $base_price, $kwh_forecast, $kwh_price);
                $this->calculateKWH($calorific_value, $cubic_meter, $condition_number);
                $this->CalculateCostActualDay($base_price, $calorific_value, $kwh_day, $kwh_price, $condition_number);
                $this->DifferenceFromInvoice($actual_counter_value, $invoice_count, $calorific_value, $condition_number);
                $calculated_forecast = $this->ForecastKWH($invoice_kwh, $invoice_date, $actual_kwh, $month_factor);
                $this->SetValue('GCM_UsedM3', $new_cubic_meter);
                $this->SetValue('GCM_CounterValue', $new_counter_value);
                $this->WriteAttributeFloat('Attrib_ActualCounterValue', $new_counter_value);
                $this->SetValue('GCM_kwhForecast', $calculated_forecast);
                $forecast_costs = $this->calculateForecastCosts($invoice_date, $base_price, $kwh_forecast, $kwh_price);
                $difference = $this->LumpSumDifference($lump_sum_year, $costs_forecast);
                $this->SetValue('GCM_CostsForecast', $forecast_costs['forecast_costs']);
                $this->SetValue('GCM_DaysSinceInvoice', $forecast_costs['days_passed']);
                $this->SetValue('GCM_DaysTillInvoice', $forecast_costs['days_remaining']);
                $this->SetValue('GCM_LumpSumDiff', $difference);
                // Debugging-Ausgaben
                $this->SendDebug('Modul.php -> actual KWH', $actual_kwh, 0);
                $this->SendDebug('Modul.php -> kwh_day_diffenerce', $kwh_day_difference, 0);
                $this->SendDebug('Modul.php -> calculated_forecast', $calculated_forecast, 0);
                $this->SendDebug('Modul.php -> lump_sum_year', $lump_sum_year, 0);
                $this->SendDebug('Modul.php -> costs_forecast', $costs_forecast, 0);
                $this->SendDebug('Modul.php -> days_since_invoice', $forecast_costs['days_passed'], 0);
                $this->SendDebug('Modul.php -> days_till_invoice', $forecast_costs['days_remaining'], 0);
            }
        }
    }