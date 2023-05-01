<?php

    declare(strict_types=1);

    require_once __DIR__ . '/../libs/CalculationHelper.php';

    class GasImpulsVerbrauchsanalyse extends IPSModule
    {
        use CalculationHelper;

        public function Create()
        {
            //Never delete this line!
            parent::Create();

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

            $this->RegisterAttributeFloat('Attrib_InstallCounterValueOld', 0);
            $this->RegisterAttributeFloat('Attrib_ActualCounterValue', 0);
            $this->RegisterAttributeFloat('Attrib_DayCount', 0);
            $this->RegisterAttributeFloat('Attrib_LumpSumPast', 0);
            $this->RegisterAttributeBoolean('Attrib_ImpulseCounted', false);

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

            $this->RegisterVariableFloat('GCM_CounterValue', $this->Translate('Current Meter Reading'), '~Gas');
            $this->RegisterVariableFloat('GCM_BasePrice', $this->Translate('Base Price'), '~Euro');
            $this->RegisterVariableFloat('GCM_UsedKWH', $this->Translate('Daily Cosnumption kW/h'), 'GCM.Gas.kWh');
            $this->RegisterVariableFloat('GCM_UsedM3', $this->Translate('Daily Cosnumption m3'), '~Gas');
            $this->RegisterVariableFloat('GCM_DayCosts', $this->Translate('Costs Today'), '~Euro');
            $this->RegisterVariableFloat('GCM_CostsYesterday', $this->Translate('Total Cost Last Day'), '~Euro');
            $this->RegisterVariableFloat('GCM_ConsumptionYesterdayKWH', $this->Translate('Total Consumption Last Day kW/h'), 'GCM.Gas.kWh');
            $this->RegisterVariableFloat('GCM_ConsumptionYesterdayM3', $this->Translate('Total Consumption Last Day m3'), '~Gas');
            $this->RegisterVariableFloat('GCM_InvoiceCounterValue', $this->Translate('Meter Reading On Last Invoice'), '~Gas');
            $this->RegisterVariableFloat('GCM_CurrentConsumption', $this->Translate('Total Consumption Actually in m3'), '~Gas');
            $this->RegisterVariableFloat('GCM_CostsSinceInvoice', $this->Translate('Costs Since Invoice'), '~Euro');
            $this->RegisterVariableFloat('GCM_KWHSinceInvoice', $this->Translate('kW/h since Invoice'), 'GCM.Gas.kWh');
            $this->RegisterVariableInteger('GCM_DaysSinceInvoice', $this->Translate('Days since Invoice'), 'GCM.Days');
            $this->RegisterVariableInteger('GCM_DaysTillInvoice', $this->Translate('Days remaining in billing period'), 'GCM.Days');
            $this->RegisterVariableFloat('GCM_CostsForecast', $this->Translate('assumed amount of the next bill'), '~Euro');
            $this->RegisterVariableFloat('GCM_kwhForecast', $this->Translate('assumed consumption level in kWh'), 'GCM.Gas.kWh');
            $this->RegisterVariableFloat('GCM_LumpSumYear', $this->Translate('Lump Sum Year'), '~Euro');
            $this->RegisterVariableFloat('GCM_LumpSumDiff', $this->Translate('Lump Sum Difference'), '~Euro');

            $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        }
        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }
        public function ApplyChanges()
        {
            parent::ApplyChanges();

            $properties = $this->readVariables();

            if (IPS_VariableExists($this->GetIDForIdent('GCM_BasePrice'))) {
                $this->SetValues([
                    'GCM_BasePrice' => $this->calculatePeriod($properties['base_price'], $properties['period'], $properties['billing_months'], $properties['invoice_date']),
                    'GCM_UsedKWH'   => $this->calculateKWH($properties['calorific_value'], $properties['cubic_meter'], $properties['condition_number']),
                    'GCM_DayCosts'  => $this->CalculateCostActualDay($properties['baseprice_day'], $properties['calorific_value'], $properties['kwh_day'], $properties['kwh_price'], $properties['condition_number'])
                ]);
            }
            if (IPS_VariableExists($this->GetIDForIdent('GCM_LumpSumYear'))) {
                $this->WriteAttributeFloat('Attrib_LumpSumPast', $properties['lump_sum']);
                $this->SetValue('GCM_LumpSumYear', $this->LumpSumYear($properties['billing_months'], $properties['lump_sum'], $properties['old_lump_sum'], $properties['invoice_date']));
            }
            if (IPS_VariableExists($this->GetIDForIdent('GCM_InvoiceCounterValue'))) {
                $Value = $this->ReadPropertyFloat('InvoiceCounterValue');
                $this->SetValue('GCM_InvoiceCounterValue', $Value);
            }
            if (IPS_VariableExists($this->GetIDForIdent('GCM_CounterValue'))) {
                if ($properties['actual_counter_value'] < $properties['install_counter_value']) {
                    $this->SetValue('GCM_CounterValue', $properties['install_counter_value']);
                }
            }
            if (IPS_VariableExists($this->GetIDForIdent('GCM_CurrentConsumption'))) {
                $invoice_difference = $this->DifferenceFromInvoice($properties['actual_counter_value'], $properties['invoice_count'], $properties['calorific_value'], $properties['condition_number']);
                $this->SetValue('GCM_KWHSinceInvoice', $invoice_difference['kwh']);
            }
            if ($properties['old_counter_value'] !== $properties['new_counter_value']) {
                $this->ImpulseCounterReset();
            }
            if (IPS_VariableExists($this->GetIDForIdent('GCM_kwhForecast'))) {
                $forecast_costs = $this->calculateForecastCosts($properties['invoice_date'], $properties['baseprice_day'], $properties['kwh_forecast'], $properties['kwh_price']);
                $this->SetValues([
                    'GCM_kwhForecast'       => $this->ForecastKWH($properties['invoice_kwh'], $properties['invoice_date'], $properties['actual_kwh'], $properties['month_factor']),
                    'GCM_CostsForecast'     => $forecast_costs['forecast_costs'],
                    'GCM_LumpSumDiff'       => $this->LumpSumDifference($properties['lump_sum_year'], $properties['costs_forecast']),
                    'GCM_DaysSinceInvoice'  => $forecast_costs['days_passed'],
                    'GCM_DaysTillInvoice'   => $forecast_costs['days_remaining']
                ]);
            }
            $eid = @$this->GetIDForIdent('GCM_EndOfDayTimer');
            if ($eid == 0) {
                $eid = IPS_CreateEvent(1);
                IPS_SetParent($eid, $this->InstanceID);
                IPS_SetIdent($eid, 'GCM_EndOfDayTimer');
                IPS_SetName($eid, $this->Translate('End Of Day Timer'));
                IPS_SetEventActive($eid, true);
                IPS_SetEventCyclic($eid, 0, 1, 0, 0, 0);
                IPS_SetEventCyclicTimeFrom($eid, 23, 59, 58);
                IPS_SetEventCyclicTimeTo($eid, 23, 59, 59);
            } else {
                IPS_SetEventCyclic($eid, 0, 1, 0, 0, 0, 0);
                IPS_SetEventCyclicTimeFrom($eid, 23, 59, 58);
                IPS_SetEventCyclicTimeTo($eid, 23, 59, 59);
            }
            IPS_SetEventScript($eid, 'GCM_DaySwitch($_IPS[\'TARGET\']);');
            IPS_SetHidden($eid, true);
            $this->GasCounter();
        }
        public function MessageSink($time_stamp, $sender_id, $Message, $Data)
        {
            switch ($Message) {
                    case VM_UPDATE:
                        $this->GasCounter();
                    break;
                default:
                    $this->SendDebug(__FUNCTION__ . ':: Messages from Sender ' . $sender_id, $Data, 0);
                    break;
                }
        }
        private function DaySwitch()
        {
            $properties = $this->readVariables();
            $this->SetValues([
                'GCM_ConsumptionYesterdayM3'    => $this->GetValue('GCM_UsedM3'),
                'GCM_ConsumptionYesterdayKWH'   => $this->GetValue('GCM_UsedKWH'),
                'GCM_CostsYesterday'            => $this->GetValue('GCM_DayCosts'),
                'GCM_UsedM3'                    => 0,
                'GCM_UsedKWH'                   => 0,
                'GCM_DayCosts'                  => 0
            ]);
            // Forecast aktualisieren
            $forecast_costs = $this->calculateForecastCosts($properties['invoice_date'], $properties['base_price'], $properties['kwh_forecast'], $properties['kwh_price']);
            $invoice_difference = $this->DifferenceFromInvoice($properties['actual_counter_value'], $properties['invoice_count'], $properties['calorific_value'], $properties['condition_number']);
            $this->setValues([
                'GCM_kwhForecast'           => $this->ForecastKWH($properties['invoice_kwh'], $properties['invoice_date'], $properties['actual_kwh'], $properties['month_factor']),
                'GCM_LumpSumDiff'           => $this->LumpSumDifference($properties['lump_sum_year'], $properties['costs_forecast']),
                'GCM_CostsForecast'         => $forecast_costs['forecast_costs'],
                'GCM_DaysSinceInvoice'      => $forecast_costs['days_passed'],
                'GCM_DaysTillInvoice'       => $forecast_costs['days_remaining'],
                'GCM_CurrentConsumption'    => $invoice_difference['result']
            ]);
        }

        private function setValues($values)
        {
            foreach ($values as $key => $value) {
                $this->SetValue($key, $value);
            }
        }

        private function updateInstallCounterValue()
        {
            $install_counter_value = $this->ReadpropertyFloat('InstallCounterValue');
            static $install_counter_value_old;
            if ($install_counter_value != $install_counter_value_old) {
                $install_counter_value_old = $install_counter_value;
            }
        }

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

        private function wasImpulseAlreadyCounted()
        {
            $counted = $this->ReadAttributeBoolean('Attrib_ImpulseCounted');
            return $counted;
        }

        private function markImpulseAsCounted()
        {
            $this->WriteAttributeBoolean('Attrib_ImpulseCounted', true);
        }

        private function ResetImpulseCountedFlag()
        {
            $this->WriteAttributeBoolean('Attrib_ImpulseCounted', false);
        }

        private function updateNewCounterValues(&$new_counter_value, &$new_cubic_meter, $properties)
        {
            if ($properties['impulse_id'] > 0) {
                $impulse = GetValue($properties['impulse_id']);
                if ($impulse && !$this->wasImpulseAlreadyCounted()) {
                    $new_counter_value = $properties['actual_counter_value'] + $properties['impulse_value'];
                    $new_cubic_meter = $properties['cubic_meter'] + $properties['impulse_value'];
                    $this->markImpulseAsCounted();
                } else {
                    $new_counter_value = $properties['actual_counter_value'];
                    $new_cubic_meter = $properties['cubic_meter'];
                }
            }
        }

        private function updateVariableValues($new_counter_value, $new_cubic_meter, $properties)
        {
            $invoice_difference = $this->DifferenceFromInvoice($properties['actual_counter_value'], $properties['invoice_count'], $properties['calorific_value'], $properties['condition_number']);
            $this->setValues([
                'GCM_UsedM3'                => $new_cubic_meter,
                'GCM_CounterValue'          => $new_counter_value,
                'GCM_DayCosts'              => $this->CalculateCostActualDay($properties['baseprice_day'], $properties['calorific_value'], $properties['kwh_day'], $properties['kwh_price'], $properties['condition_number']),
                'GCM_CostsSinceInvoice'     => $this->calculateCosts($properties['baseprice_day'], $properties['invoice_date'], $properties['actual_kwh'], $properties['kwh_price']),
                'GCM_UsedKWH'               => $this->calculateKWH($properties['calorific_value'], $properties['cubic_meter'], $properties['condition_number']),
                'GCM_CurrentConsumption'    => $invoice_difference['result'],
                'GCM_KWHSinceInvoice'       => $invoice_difference['kwh']
            ]);
        }

        private function GasCounter()
        {
            // Registrieren der Änderungsbenachrichtigung für den Impuls
            $this->RegisterMessage($this->ReadPropertyInteger('ImpulseID'), VM_UPDATE);
            $this->ResetImpulseCountedFlag();

            // Lesen der benötigten Variablen
            $properties = $this->readVariables();
            $calculated_forecast = 0;
            $kwh_day_difference = 0;

            // Aktualisierung bei Anpassung Zählerstand bei Installation
            $this->updateInstallCounterValue();
            $install_counter_value = $this->ReadPropertyFloat('InstallCounterValue');

            // Berechnung der neuen Zählerwerte
            $new_counter_value = 0;
            $new_cubic_meter = 0;
            $this->updateNewCounterValues($new_counter_value, $new_cubic_meter, $properties);

            // Aktualisierung der Variablenwerte
            $this->updateVariableValues($new_counter_value, $new_cubic_meter, $properties);

            $this->WriteAttributeFloat('Attrib_ActualCounterValue', $new_counter_value);
        }

        private function readVariables()
        {
            return [
                'invoice_date'          => $this->ReadpropertyString('InvoiceDate'),
                'invoice_kwh'           => $this->ReadPropertyInteger('InvoiceKWH'),
                'kwh_price'             => $this->ReadpropertyFloat('KWHPrice'),
                'month_factor'          => $this->ReadPropertyString('MonthFactor'),
                'base_price'            => $this->ReadPropertyFloat('BasePrice'),
                'calorific_value'       => $this->ReadpropertyFloat('CalorificValue'),
                'condition_number'      => $this->ReadPropertyFloat('ConditionNumber'),
                'lump_sum'              => $this->ReadPropertyFloat('LumpSum'),
                'lump_sum_year'         => $this->GetValue('GCM_LumpSumYear'),
                'costs_forecast'        => $this->GetValue('GCM_CostsForecast'),
                'actual_kwh'            => $this->GetValue('GCM_KWHSinceInvoice'),
                'kwh_forecast'          => $this->GetValue('GCM_kwhForecast'),
                'cubic_meter'           => $this->GetValue('GCM_UsedM3'),
                'actual_counter_value'  => $this->GetValue('GCM_CounterValue'),
                'impulse_id'            => $this->ReadPropertyInteger('ImpulseID'),
                'impulse_value'         => $this->ReadPropertyFloat('ImpulseValue'),
                'install_counter_value' => $this->ReadPropertyFloat('InstallCounterValue'),
                'invoice_count'         => $this->ReadPropertyFloat('InvoiceCounterValue'),
                'kwh_day'               => $this->GetValue('GCM_UsedKWH'),
                'month_factor'          => $this->ReadPropertyString('MonthFactor'),
                'old_lump_sum'          => $this->ReadAttributeFloat('Attrib_LumpSumPast'),
                'billing_months'        => $this->ReadPropertyInteger('BillingMonths'),
                'old_counter_value'     => $this->ReadAttributeFloat('Attrib_InstallCounterValueOld'),
                'new_counter_value'     => $this->ReadPropertyFloat('InstallCounterValue'),
                'base_price_period'     => $this->ReadPropertyString('BasePricePeriod'),
                'period'                => $this->ReadPropertyString('BasePricePeriod'),
                'baseprice_day'         => $this->GetValue('GCM_BasePrice')
            ];
            foreach ($variables as $key => $value) {
                $this->SendDebug($key, 'Value: ' . $value, 0);
            }
            return $variables;
        }
    }