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
            // $this->RegisterAttributeFloat('Attrib_UsedKWH', 0);
            $this->RegisterAttributeFloat('Attrib_UsedM3', 0);
            // $this->RegisterAttributeFloat('Attrib_DayCosts', 0);
            $this->RegisterAttributeFloat('Attrib_CounterValue', 0);
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

            //Messages
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
            $this->RegisterEvent();

            // Impuls Verwertung
            $this->ImpulseCount();
        }
    }