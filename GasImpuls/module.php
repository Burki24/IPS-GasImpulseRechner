<?php

    declare(strict_types=1);

    require_once __DIR__ . '/../libs/VariableProfileHelper.php';

    class GasImpulsVerbrauchsanalyse extends IPSModule
    {
        use VariableProfileHelper;

        public function Create()
        {
            //Never delete this line!
            parent::Create();

            //Werte aus dem Formular
            $this->RegisterPropertyInteger('ImpulseID', 0);
            $this->RegisterPropertyFloat('ImpulseValue', 0);
            $this->RegisterPropertyFloat('BasePrice', 0);
            $this->RegisterPropertyString('BasePricePeriod', 'month');
            $this->RegisterPropertyFloat('CalorificValue', 0);
            $this->RegisterPropertyFloat('InvoiceCounterValue', 0);
            $this->RegisterPropertyString('InvoiceDate', $this->getCurrentDate());
            $this->RegisterPropertyFloat('InstallCounterValue', 0);
            $this->RegisterPropertyFloat('KWHPrice', 0);
            $this->RegisterPropertyFloat('InstallDayCount', 0);

            // Zur Berechnung bereitzustellende Werte
            $this->RegisterAttributeFloat('Attrib_InstallCounterValueOld', 0);
            $this->RegisterAttributeFloat('Attrib_UsedM3', 0);
            $this->RegisterAttributeFloat('Attrib_DayCosts', 0);
            $this->RegisterAttributeFloat('Attrib_ActualCounterValue', 0);
            // $this->RegisterAttributeFloat('Attrib_CostsYesterday', 0);
            // $this->RegisterAttributeFloat('Attrib_ConsumptionYesterdayKWH', 0);
            $this->RegisterAttributeFloat('Attrib_ConsumptionYesterdayM3', 0);
            $this->RegisterAttributeBoolean('Attrib_ImpulseState', 0);
            $this->RegisterAttributeFloat('Attrib_DayCount', 0);
            // $this->RegisterAttributeFloat('Attrib_DayValue', 0);

            // Profil erstellen
            if (!IPS_VariableProfileExists('GCM.Gas.kWh')) {
                $this->RegisterProfileFloat('GCM.Gas.kWh', 'Flame', 0, ' kWh', 0, 0, 0, 2);
            }

            // Variablen erstellen
            $this->RegisterVariableFloat('GCM_UsedKWH', $this->Translate('Daily Cosnumption kW/h'), 'GCM.Gas.kWh');
            $this->RegisterVariableFloat('GCM_UsedM3', $this->Translate('Daily Cosnumption m3'), '~Gas');
            $this->RegisterVariableFloat('GCM_DayCosts', $this->Translate('Costs Today'), '~Euro');
            $this->RegisterVariableFloat('GCM_CounterValue', $this->Translate('Current Meter Reading'), '~Gas');
            $this->RegisterVariableFloat('GCM_CurrentConsumption', $this->Translate('Total Consumption Actually in m3'), '~Gas');
            $this->RegisterVariableFloat('GCM_CostsYesterday', $this->Translate('Total Cost Last Day'), '~Euro');
            $this->RegisterVariableFloat('GCM_ConsumptionYesterdayKWH', $this->Translate('Total Consumption Last Day kW/h'), 'GCM.Gas.kWh');
            $this->RegisterVariableFloat('GCM_ConsumptionYesterdayM3', $this->Translate('Total Consumption Last Day m3'), '~Gas');
            $this->RegisterVariableFloat('GCM_BasePrice', $this->Translate('Base Price'), '~Euro');
            $this->RegisterVariableFloat('GCM_InvoiceCounterValue', $this->Translate('Meter Reading On Last Invoice'), '~Gas');
            $this->RegisterVariableFloat('GCM_CostsSinceInvoice', $this->Translate('Costs Since Invoice'), '~Euro');

            // Messages
            $this->RegisterMessage(0, IPS_KERNELMESSAGE);

            // Event einrichten Tagesende
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

            // Eintragung des kalkulierten Grundpreises
            if (IPS_VariableExists($this->GetIDForIdent('GCM_BasePrice'))) {
                $value = $this->ReadPropertyFloat('BasePrice');
                $period = $this->ReadPropertyString('BasePricePeriod');
                $result = $this->calculatePeriod($value, $period);
                $this->SetValue('GCM_BasePrice', $result);
            }

            // Eintragung Zählerstand bei Rechnungsstellung
            if (IPS_VariableExists($this->GetIDForIdent('GCM_InvoiceCounterValue'))) {
                $Value = $this->ReadPropertyFloat('InvoiceCounterValue');
                $this->SetValue('GCM_InvoiceCounterValue', $Value);
            }

            // Errechnung Zählerstanddifferenz bei Installation
            if (IPS_VariableExists($this->GetIDForIdent('GCM_CurrentConsumption'))) {
                $this->DifferenceFromInvoice();
            }

            // ImpulseCounter zurücksetzen
            $oldCounterValue = $this->ReadAttributeFloat('Attrib_InstallCounterValueOld');
            $newCounterValue = $this->ReadPropertyFloat('InstallCounterValue');
            if ($oldCounterValue !== $newCounterValue) {
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
            IPS_SetEventScript($eid, 'GCM_DayEnd($_IPS[\'TARGET\']);');

            // Impuls Verwertung
            // $this->ImpulseCount();
        }
        public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
        {
            IPS_LogMessage('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true));
            switch ($Message) {
                    case VM_UPDATE:
                        $impulseID = $this->ReadPropertyInteger('ImpulseID');
                        $impulseState = GetValue($impulseID);
                        $cubicMeter = $this->GetValue('GCM_UsedM3');
                        $calorificValue = $this->ReadPropertyFloat('CalorificValue');
                        $this->WriteAttributeBoolean('Attrib_ImpulseState', $impulseState);
                        $this->SendDebug('Impulse State Message', $impulseState, 0);
                        $this->GasCounter();
                        $this->CostsSinceInvoice();
                        $this->calculateKWH($calorificValue, $cubicMeter);
                        $this->CalculateCostActualDay();
                        $this->DifferenceFromInvoice();
                        $this->DayCounter();
                    break;
                default:
                    $this->SendDebug(__FUNCTION__ . ':: Messages from Sender ' . $SenderID, $Data, 0);
                    break;
                }
        }

        // Aktuelles Datum berechnen
        private function getCurrentDate()
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
        private function CalculateCostActualDay()
        {
            $baseCosts = (round($this->GetValue('GCM_BasePrice'), 2));
            $calorificValue = $this->ReadpropertyFloat('CalorificValue');
            $kwh = round($this->GetValue('GCM_UsedKWH'), 3);
            $kwhCosts = $kwh * $this->ReadpropertyFloat('KWHPrice');
            $costs = $kwhCosts + $baseCosts;
            $this->SetValue('GCM_DayCosts', $costs);
        }
        private function updateInstallCounterValue()
        {
            $InstallCounterValue = $this->ReadpropertyFloat('InstallCounterValue');
            static $InstallCounterValueOld;
            if ($InstallCounterValue != $InstallCounterValueOld) {
                // Aktion durchführen
                $InstallCounterValueOld = $InstallCounterValue;
            }
        }
        // Berechnung Verbrauch seit Ablesung in m3
        private function DifferenceFromInvoice()
        {
            $actual = $this->GetValue('GCM_CounterValue');
            $invoice = $this->ReadPropertyFloat('InvoiceCounterValue');
            $result = ($actual - $invoice);
            $this->SetValue('GCM_CurrentConsumption', $result);
        }
        // Counterreset wenn InstallCounter geändert
        private function ImpulseCounterReset()
        {
            $oldCounterValue = $this->ReadAttributeFloat('Attrib_InstallCounterValueOld');
            $newCounterValue = $this->ReadPropertyFloat('InstallCounterValue');
            $newDayCount = $this->ReadPropertyFloat('InstallDayCount');
            $this->SetValue('GCM_UsedM3', $newDayCount);
            $this->WriteAttributeFloat('Attrib_InstallCounterValueOld', $newCounterValue);
            // $this->WriteAttributeFloat('Attrib_CounterValue', 0);
            // Debug
            $this->SendDebug('Install Day Count', $newDayCount, 0);
        }
        private function ImpulseCount()
        {
            $impulseID = $this->ReadPropertyInteger('ImpulseID');
            if ($impulseID && $impulseID > 0) {
                $impulseState = GetValue($impulseID);
                $this->SendDebug('Impulse Status', $impulseState, 0);
                $this->WriteAttributeBoolean('Attrib_ImpulseState', $impulseState);
                $this->SetValue('GCM_CounterValue', $this->ReadpropertyFloat('InstallCounterValue'));
                $this->SetValue('GCM_UsedM3', $this->ReadPropertyFloat('InstallDayCount'));
                $this->WriteAttributeFloat('Attrib_DayCount', $this->ReadAttributeFloat('Attrib_UsedM3'));
                $this->SendDebug('Attribute DayCount', $this->ReadAttributeFloat('Attrib_DayCount'), 0);
                $this->GasCounter();
                $this->SendDebug('CounterValue', $this->ReadAttributeFloat('Attrib_ActualCounterValue'), 0);
                $this->SendDebug('installCounterValue', $this->ReadpropertyFloat('InstallCounterValue'), 0);
                $this->CalculateCostActualDay();
            }
        }
        private function GasCounter()
        {
            $this->RegisterMessage($this->ReadPropertyInteger('ImpulseID'), VM_UPDATE);
            $impulseID = $this->ReadPropertyInteger('ImpulseID');
            $impulseValue = $this->ReadPropertyFloat('ImpulseValue');
            $impulseAttrib = $this->ReadAttributeBoolean('Attrib_ImpulseState');
            $this->SendDebug('Impulse Status GasCounter', $impulseAttrib, 0);
            $counterValue = $this->ReadAttributeFloat('Attrib_ActualCounterValue');
            $cubicMeter = $this->GetValue('GCM_UsedM3');
            $installCounterValue = $this->ReadPropertyFloat('InstallCounterValue');
            $currentCounterValue = $this->GetValue('GCM_CounterValue');
            $this->updateInstallCounterValue();
            $installCounterValue = $this->ReadpropertyFloat('InstallCounterValue');
            $final = $installCounterValue; // initialisieren Sie die Variable $final mit dem Wert von $installCounterValue
            $finalDay = $this->ReadPropertyFloat('InstallDayCount');
            $impulse = GetValue($impulseID);
            if ($impulse) {
                // Wenn $impulse = true ist, erhöhen Sie den aktuellen Zählerstand um $impulseValue
                $newCounterValue = $currentCounterValue + $impulseValue;
                $newCubicMeter = $cubicMeter + $impulseValue;
            } else {
                // Wenn $impulse = false ist, verwenden Sie den aktuellen Zählerstand ohne Erhöhung
                $newCounterValue = $currentCounterValue;
                $newCubicMeter = $cubicMeter;
            }
            // Setzen Sie die Werte in die Werte
            $this->SetValue('GCM_CounterValue', $newCounterValue);
            $this->SetValue('GCM_UsedM3', $newCubicMeter);
            $this->WriteAttributeBoolean('Attrib_ImpulseState', $impulse);
        }
    }