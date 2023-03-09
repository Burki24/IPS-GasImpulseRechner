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


			// Zur Berechnung bereitzustellende Werte
			$this->RegisterAttributeFloat('Attrib_InstallCounterValueOld', 0);
			$this->RegisterAttributeFloat('Attrib_UsedKWH', 0);
			$this->RegisterAttributeFloat('Attrib_UsedM3', 0);
			$this->RegisterAttributeFloat('Attrib_DayCosts', 0);
			$this->RegisterAttributeFloat('Attrib_CounterValue', 0);
			$this->RegisterAttributeFloat('Attrib_CostsYesterday', 0);
			$this->RegisterAttributeFloat('Attrib_ConsumptionYesterdayKWH', 0);
			$this->RegisterAttributeFloat('Attrib_ConsumptionYesterdayM3', 0);
			$this->RegisterAttributeBoolean('Attrib_ImpulseState', 0);

			// Profil erstellen
			// if (!IPS_VariableProfileExists('GCM.Gas.kWh')) {
			$this->RegisterProfileFloat('GCM.Gas.kWh', 'Flame', 0, ' kWh', 0, 0, 0, 2);
			// }

			// Variablen erstellen
			$this->RegisterVariableFloat("GCM_UsedKWH", $this->Translate('Daily Cosnumption kW/h'), "GCM.Gas.kWh");
			$this->RegisterVariableFloat("GCM_UsedM3", $this->Translate('Daily Cosnumption m3'), "~Gas");
			$this->RegisterVariableFloat("GCM_DayCosts", $this->Translate('Costs Today'), "~Euro");
			$this->RegisterVariableFloat("GCM_CounterValue", $this->Translate('Current Meter Reading'), "~Gas");
			$this->RegisterVariableFloat("GCM_CurrentConsumption", $this->Translate('Total Consumption Actually in m3'), "~Gas");
			$this->RegisterVariableFloat("GCM_CostsYesterday", $this->Translate('Total Cost Last Day'), "~Euro");
			$this->RegisterVariableFloat("GCM_ConsumptionYesterdayKWH", $this->Translate('Total Consumption Last Day kW/h'), "GCM.Gas.kWh");
			$this->RegisterVariableFloat("GCM_ConsumptionYesterdayM3", $this->Translate('Total Consumption Last Day m3'), "~Gas");
			$this->RegisterVariableFloat("GCM_BasePrice", $this->Translate('Base Price'), "~Euro");
			$this->RegisterVariableFloat("GCM_InvoiceCounterValue", $this->Translate('Meter Reading On Last Invoice'), "~Gas");
			$this->RegisterVariableFloat("GCM_CostsSinceInvoice", $this->Translate('Costs Since Invoice'), "~Euro");

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
    		if (IPS_VariableExists($this->GetIDForIdent("GCM_BasePrice"))) {
				$this->BasePriceCalculation();
    		}

			// Eintragung Zählerstand bei Rechnungsstellung
    		if (IPS_VariableExists($this->GetIDForIdent("GCM_InvoiceCounterValue"))) {
				$this->InvoiceCounterValue();
			}

			// Errechnung Zählerstanddifferenz bei Installation
    		if (IPS_VariableExists($this->GetIDForIdent("GCM_CurrentConsumption"))) {
				$this->Difference();
			}

			// ImpulseCounter zurücksetzen
			$old = ReadAttributeFloat('Attrib_InstallCounterValueOld'), 0);
			$new = $this->ReadPropertyFloat('InstallCounterValue');
			If ($old !== $new) {
			$this->WriteAttributeFloat('Attrib_InstallCounterValueOld', $this->ReadPropertyFloat('InstallCounterValue'));
			$this->SendDebug("Counter old", $this->ReadAttributeFloat('Attrib_InstallCounterValueOld'), 0);
			$this->SendDebug("Counter new", $this->ReadAttributeFloat('InstallCounterValue'), 0);
			}
			// Event Tagesende starten
				$this->RegisterEvent();

			// Impuls Verwertung
				$this->ImpulseCount();
		}
		// MessageSink
		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
		{
    		IPS_LogMessage("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true));
				// $counterValue = $this->ReadAttributeFloat('Attrib_UsedM3');
				switch ($Message) {
					case VM_UPDATE:
						$impulseCounter = $this->ReadPropertyInteger('ImpulseID');
						$impulseState = GetValue($impulseCounter);
						$this->WriteAttributeBoolean('Attrib_ImpulseState', $impulseState);
						$this->GasCounter();
						$this->CostsSinceInvoice();
						$this->CostActualDay();
						$this->Difference();
					break;
                default:
                    $this->SendDebug(__FUNCTION__ . ':: Messages from Sender ' . $SenderID, $Data, 0);
                    break;
				}
		}
		private function BasePriceCalculation()
		{
			$value = $this->ReadPropertyFloat('BasePrice');
			$period = $this->ReadPropertyString('BasePricePeriod');
			$result = $this->calculatePeriod($value, $period);
			$this->SetValue("GCM_BasePrice", $result);
		}
		private function InvoiceCounterValue()
		{
			$Value = $this->ReadPropertyFloat('InvoiceCounterValue');
			$this->SetValue("GCM_InvoiceCounterValue", $Value);
		}
		private function Difference()
		{
			$actual = $this->GetValue("GCM_CounterValue");
			$invoice = $this->ReadPropertyFloat('InvoiceCounterValue');
			$result = ($actual - $invoice);
			$this->SetValue("GCM_CurrentConsumption", $result);
		}
		private function GasCounter()
		{
    		$this->RegisterMessage($this->ReadPropertyInteger('ImpulseID'), VM_UPDATE);
    		$impulseID = $this->ReadPropertyInteger('ImpulseID');
    		$impulseValue = $this->ReadPropertyFloat('ImpulseValue');
    		$calorificValue = $this->ReadPropertyFloat('CalorificValue');
    		$impulseProvider = $this->ReadPropertyInteger('ImpulseID');
    		$impulse = GetValue($impulseProvider);
    		$impulseAttrib = $this->ReadAttributeBoolean('Attrib_ImpulseState');
    		$counterValue = $this->ReadAttributeFloat('Attrib_CounterValue');

    		$this->updateInstallCounterValue();

    		$installCounterValue = round($this->ReadpropertyFloat('InstallCounterValue'), 2);
    		//$installCounterValueOld = $this->ReadAttributeFloat('Attrib_InstallCounterValueOld');
    		$final = $installCounterValue; // initialisieren Sie die Variable $final mit dem Wert von $installCounterValue

    		// if ($installCounterValue != $installCounterValueOld) {
        		// $counterValue = 0; // setzen Sie den Wert von $counterValue auf Null, wenn $installCounterValue geändert wird
    		// }

    		if ($impulse) {
        		$final = $installCounterValue + $counterValue + $impulseValue; // addieren Sie den Wert von $impulseValue und $counterValue zu $installCounterValue hinzu, um den aktuellen Zählerstand zu erhalten
        		$counterValue += $impulseValue; // erhöhen Sie den Wert von $counterValue um $impulseValue
        		$this->WriteAttributeFloat('Attrib_CounterValue', $counterValue); // speichern Sie den aktualisierten Wert von $counterValue in den Attributen
        		$this->SetValue("GCM_CounterValue", $final);
    		}

    		$this->WriteAttributeBoolean('Attrib_ImpulseState', $impulse);
		}

		private function CostsSinceInvoice()
		{
			$json = $this->ReadpropertyString('InvoiceDate');
			$date = json_decode($json, true);
			$timestamp = mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
			$days_since = floor((time() - $timestamp) / (60 * 60 * 24));
			$baseCosts = (round($this->GetValue("GCM_BasePrice"), 2) * $days_since);
			$calorificValue = $this->ReadpropertyFloat('CalorificValue');
			$kwh = round($this->GetValue("GCM_CurrentConsumption") * $calorificValue, 3);
			$kwhCosts = $kwh * $this->ReadpropertyFloat('KWHPrice');
			$costs = $kwhCosts + $baseCosts;
			$this->SetValue("GCM_CostsSinceInvoice", $costs);
			// $this->SendDebug("Grundpreis seit Rechnungsstellung", $baseCosts, 0);
			// $this->SendDebug("Gasverbrauchspreis", $this->GetValue("GCM_BasePrice"), 0);
			// $this->SendDebug("kwh", $kwh, 0);
			// $this->SendDebug("json", $json, 0);
			// $this->SendDebug("Tage seit Abrechnung", $days_since, 0);
		}
		private function CostActualDay()
		{
			$baseCosts = (round($this->GetValue("GCM_BasePrice"), 2));
			$calorificValue = $this->ReadpropertyFloat('CalorificValue');
			$kwh = round($this->GetValue("GCM_UsedKWH"), 3);
			$kwhCosts = $kwh * $this->ReadpropertyFloat('KWHPrice');
			$costs = $kwhCosts + $baseCosts;
			$this->SetValue("GCM_DayCosts", $costs);
		}

		public function timerSetting()
		{
    		// Speichern der gestrigen Verbrauchswerte
    		$this->SetValue("GCM_ConsumptionYesterdayM3", $this->GetValue("GCM_UsedM3"));
    		$this->SetValue("GCM_ConsumptionYesterdayKWH", $this->GetValue("GCM_UsedKWH"));
    		$this->SetValue("GCM_CostsYesterday", $this->GetValue("GCM_DayCosts"));
    		$this->SetValue("GCM_UsedM3", 0);
    		$this->SetValue("GCM_UsedKWH", 0);
    		$this->SetValue("GCM_DayCosts", 0);
		}
		// Property-Funktionen
		private function calculatePeriod($value, $period)
    	{
        	// Berechnung Schaltjahr
        	$daysInYear = 365;
        	if (checkdate(2, 29, (int) date("Y"))) {
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
		private function calculateKWH($calorificValue, $cubicMeter)
    	{
        	$kwh = $calorificValue * $cubicMeter;
        	return $kwh;
    	}
		private function getCurrentDate()
		{
			$date = date('Y-m-d');
			list($year, $month, $day) = explode('-', $date);

			$dateArray = array(
				'year' => (int)$year,
				'month' => (int)$month,
				'day' => (int)$day
			);

			return json_encode($dateArray);
		}
		private function RegisterEvent()
		{
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
    		IPS_SetEventScript($eid, 'GCM_timerSetting($_IPS[\'TARGET\']);');
    		return $eid;
		}
		function updateInstallCounterValue()
		{
			$InstallCounterValue = $this->ReadpropertyFloat('InstallCounterValue');
			static $InstallCounterValueOld;
			if ($InstallCounterValue != $InstallCounterValueOld) {
				// Aktion durchführen
				$InstallCounterValueOld = $InstallCounterValue;
			}
		}
		private function ImpulseCount()
		{
			$impulseID = $this->ReadPropertyInteger('ImpulseID');
			if($impulseID && $impulseID > 0) {
				$impulseState = GetValue($impulseID);
				$installCounterValueOld = $this->ReadpropertyFloat('InstallCounterValue');
				$this->WriteAttributeBoolean('Attrib_ImpulseState', $impulseState);
				// $this->WriteAttributeFloat('Attrib_InstallCounterValueOld', $installCounterValueOld);
				$this->SetValue("GCM_CounterValue", $this->ReadpropertyFloat('InstallCounterValue'));
				$this->SetValue("GCM_UsedM3", $this->ReadAttributeFloat('Attrib_CounterValue'));
				$this->GasCounter();
				$this->SendDebug("CounterValue", $this->ReadAttributeFloat('Attrib_CounterValue'), 0);
				// $this->SendDebug("installCounterValueOld", $this->ReadAttributeFloat('Attrib_InstallCounterValueOld'), 0);
				$this->SendDebug("installCounterValue", $this->ReadpropertyFloat('InstallCounterValue'), 0);
			}
		}
			//Impulse resett bei Änderung von InstallCounterValue

		}
