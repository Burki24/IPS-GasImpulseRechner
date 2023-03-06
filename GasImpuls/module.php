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
			$this->RegisterPropertyInteger('ImpulseProvider', 0);
			$this->RegisterPropertyString('BasePricePeriod', 'month');
			$this->RegisterPropertyFloat('ImpulseValue', 0);
			$this->RegisterPropertyFloat('BasePrice', 0);
			$this->RegisterPropertyFloat('CalorificValue', 0);
			$this->RegisterPropertyFloat('InvoiceCounterValue', 0);
			$this->RegisterPropertyFloat('InstallCounterValue', 0);
			$this->RegisterPropertyFloat('PriceValueKWH', 0);
			$this->RegisterPropertyString('InvoiceDate', $this->getCurrentDate());

			// Zur Berechnung bereitzustellende Werte
			$this->RegisterAttributeFloat('Attrib_UsedKWH', 0);
			$this->RegisterAttributeFloat('Attrib_UsedM3', 0);
			$this->RegisterAttributeFloat('Attrib_DayCosts', 0);
			$this->RegisterAttributeFloat('Attrib_CounterValue', 0);
			$this->RegisterAttributeFloat('Attrib_CostsYesterday', 0);
			$this->RegisterAttributeFloat('Attrib_ConsumptionYesterdayKWH', 0);
			$this->RegisterAttributeFloat('Attrib_ConsumptionYesterdayM3', 0);
			$this->RegisterAttributeBoolean('Attrib_ImpulseState', 0);

			// Profil erstellen
			if (!IPS_VariableProfileExists('GCM.Gas.kWh')) {
			$this->RegisterProfileFloat('GCM.Gas.kWh', 'Flame', 0, ' kWh', 0, 0, 0, 2);
			}

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

			//Timer
			$this->RegisterTimer('CloseDay', 0, 'GCM_timerSetting($_IPS[\'TARGET\']);');
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
			// Register starten
			if (IPS_VariableExists($this->GetIDForIdent("GCM_CurrentConsumption"))) {
				$this->RegisterEvent();
			}
			// Impuls Verwertung

			$impulseProvider = $this->ReadPropertyInteger('ImpulseProvider');
			if($impulseProvider && $impulseProvider > 0) {
				$impulseProvider = $this->ReadPropertyInteger('ImpulseProvider');
				$impulseState = GetValue($impulseProvider);
				$this->WriteAttributeBoolean('Attrib_ImpulseState', $impulseState);
				$this->GasCounter();
				$installCounterValue = $this->ReadpropertyFloat('InstallCounterValue');
				$this->SetValue("GCM_CounterValue", $installCounterValue);
				$this->SetValue("GCM_UsedM3", $this->ReadAttributeFloat('Attrib_CounterValue'));
				$this->SendDebug("CounterValue", $this->ReadAttributeFloat('Attrib_CounterValue'), 0);
				$this->SendDebug("Tageszähler", $this->ReadAttributeFloat('Attrib_CounterValue'), 0);
			}
		}

		// MessageSink
		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
		{
    		IPS_LogMessage("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true));
				$counterValue = $this->ReadAttributeFloat('Attrib_UsedM3');
				switch ($Message) {
					case VM_UPDATE:
						$impulseCounter = $this->ReadPropertyInteger('ImpulseProvider');
						$impulseState = GetValue($impulseCounter);
						$this->WriteAttributeBoolean('Attrib_ImpulseState', $impulseState);
						$this->GasCounter();
						$this->CostsSinceInvoice();
						$this->CostActualDay();
						$this->Difference();
						// $this->timerSetting();
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
    		$this->RegisterMessage($this->ReadPropertyInteger('ImpulseProvider'), VM_UPDATE);
    		$impulseID = $this->ReadPropertyInteger('ImpulseProvider');
    		$impulseValue = $this->ReadPropertyFloat('ImpulseValue');
    		$installCounterValue = round($this->ReadpropertyFloat('InstallCounterValue'), 2);
    		$lastInstallCounterValue = round($this->GetBuffer("lastInstallCounterValue"), 2);
    		$lastCalculation = round($this->GetBuffer("calculation"), 2);
			$calorificValue = $this->ReadPropertyFloat('CalorificValue');
			$impulseProvider = $this->ReadPropertyInteger('ImpulseProvider');
			$impulseState = GetValue($impulseProvider);
			$impulseAttrib = $this->ReadAttributeBoolean('Attrib_ImpulseState');
			$this->SetBuffer("installCounterValue", $installCounterValue);
    		if ($impulseState) {
        		$result = $this->GetBuffer("calculation") + $impulseValue;
        		$this->SetBuffer("calculation", round($result, 2));
        		$finalResult = $this->GetBuffer("installCounterValue") + round($result, 2);
				// $this->SendDebug("$finalResult", $finalResult, 0);
        		$this->SetValue("GCM_CounterValue", round($finalResult, 2));
        		// $this->SendDebug("Stand aktuell", round($result, 2), 0);
				$this->WriteAttributeFloat('Attrib_UsedM3', $result);
				$this->SetValue("GCM_UsedM3", $result);
				$calorificValue = $this->ReadPropertyFloat('CalorificValue');
				// $this->SendDebug("Faktor", $calorificValue, 0);
				$cubicMeter = $this->GetValue("GCM_UsedM3");
				// $this->SendDebug("M3", $cubicMeter, 0);
				$yesterdaykwh = $calorificValue * $cubicMeter;
				$this->SetValue("GCM_UsedKWH", $yesterdaykwh);
				$this->SendDebug("Yesterday kwh", $yesterdaykwh, 0);
        		// $this->SendDebug("Stand aktuell Final", round($finalResult, 2), 0);
				$this->WriteAttributeFloat('Attrib_CounterValue', round($result, 2));
    		}
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
			$kwhCosts = $kwh * $this->ReadpropertyFloat('PriceValueKWH');
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
			$kwhCosts = $kwh * $this->ReadpropertyFloat('PriceValueKWH');
			$costs = $kwhCosts + $baseCosts;
			$this->SetValue("GCM_DayCosts", $costs);
		}

		public function timerSetting($target)
		{
    		// Speichern der gestrigen Verbrauchswerte
    		$this->SetValue("GCM_ConsumptionYesterdayM3", $this->GetValue("GCM_UsedM3"));
    		$this->SetValue("GCM_ConsumptionYesterdayKWH", $this->GetValue("GCM_UsedKWH"));
    		$this->SetValue("GCM_CostsYesterday", $this->GetValue("GCM_DayCosts"));
    		$this->SetValue("GCM_UsedM3", 0);
    		$this->SetValue("GCM_UsedKWH", 0);
    		$this->SetValue("GCM_DayCosts", 0);

    		// Generieren des Berichts
    		// $this->_generateReport($target, $reportStartDate, $reportEndDate, $param1, $param2, $param3, $param4);
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
    		$ReportStartDate = 'midnight first day of last month';
    		$ReportEndDate = 'first day of this month';

    		$eid = @$this->GetIDForIdent('EndOfDayTimer');
    		if ($eid == 0) {
        		$eid = IPS_CreateEvent(1);
        		IPS_SetParent($eid, $this->InstanceID);
        		IPS_SetIdent($eid, 'EndOfDayTimer');
        		IPS_SetName($eid, $this->Translate('End Of Day Timer'));

        		IPS_SetEventCyclic($eid, 1 /* Täglich */, 1 /* Jeder Tag */, 0 /* Egal welcher Wochentag */, 0 /* Egal welcher Tag im Monat */, 0, 0);
        		IPS_SetEventCyclicTimeFrom($eid, 17, 45, 00);
        		IPS_SetEventCyclicTimeTo($eid, 17, 45, 00);
    		} else {
        		IPS_SetEventCyclic($eid, 3 /* Täglich */, 1 /* Jeder Tag */, 0 /* Egal welcher Wochentag */, 0 /* Egal welcher Tag im Monat */, 0, 0);
        		IPS_SetEventCyclicTimeFrom($eid, 17, 45, 00);
        		IPS_SetEventCyclicTimeTo($eid, 17, 45, 00);
    		}
    		IPS_SetEventScript($eid, 'GCM_timerSetting($_IPS[\'TARGET\']);');
    		return $eid;
		}
	}