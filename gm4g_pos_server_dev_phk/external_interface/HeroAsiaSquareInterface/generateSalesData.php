<?php
	require ('DBControl.php');

	//read setup file
	function readSetup() {
		$setup = array();
		$sSetupFileName = "setup.txt";
		
		if (!file_exists($sSetupFileName))
			return false;
		
		$fSetupFile = fopen($sSetupFileName, "r");
		if($fSetupFile != NULL) {
			while(!feof($fSetupFile)) {
				$setupText = fgets($fSetupFile);
				$tok = strtok($setupText, "=\r\n");
				if($tok !== false) {
					$setupName = $tok;
					$tok = strtok("=\r\n");
					if($tok !== false) {
						$setup[$setupName] = $tok;
					}else {
						$setup[$setupName] = "";
					}
				}
			}
		}
		fclose($fSetupFile);
		
		return $setup;
	}
	
	//get file serial number
	function getFileSerialNumber($bday) {
		$iSerialNumber = 0;
		$serialNumberArray = array();
		//$sSerialNumberFileName = substr($bday, 0, 6).".txt";
		$sSerialNumberFileName = "serialNumber.txt";
		
		if (file_exists($sSerialNumberFileName)) {
			$fSerialNumberFile = fopen($sSerialNumberFileName, "r");
			if($fSerialNumberFile != NULL) {
				if (!feof($fSerialNumberFile)) {
					$serialNumberText = fgets($fSerialNumberFile);
					$tok = strtok($serialNumberText, ",\r\n");
					if ($tok !== false) {
						$iSerialNumber = $tok;
					}
				}
				fclose($fSerialNumberFile);
				/*while (!feof($fSerialNumberFile)) {
					$serialNumberText = fgets($fSerialNumberFile);
					$tok = strtok($serialNumberText, ",\r\n");
					if ($tok !== false) {
						$date = $tok;
						$tok = strtok(",\r\n");
						$dateSerialNumber = $tok;
						
						$serialNumberArray[$date] = $dateSerialNumber;
					}
				}*/
			}
		}
		
		/*if(isset($serialNumberArray[$bday])) {
			$iSerialNumber = $serialNumberArray[$bday];
			$serialNumberArray[$bday] = $iSerialNumber+1;
		}else
			$serialNumberArray[$bday] = 1;*/
		
		$fSerialNumberFile = fopen($sSerialNumberFileName, "w");
		/*foreach($serialNumberArray as $date => $dateSerialNumber)
			fputs($fSerialNumberFile, $date.",".$dateSerialNumber."\r\n");*/
		if($iSerialNumber == 999)
			fputs($fSerialNumberFile, "0\r\n");
		else
			fputs($fSerialNumberFile, ($iSerialNumber+1)."\r\n");
		fclose($fSerialNumberFile);
		
		return $iSerialNumber;
	}
	
	//get the check total (not include discount amount and inclusive tax)
	function getSalesAmount(&$database, $posDbName, $outletDbName, $dbIPAddress, $dbLogin, $dbPassword, $bday, $shopCode, $outletCode, $setup, $sSalesDataFileName, $paymentListType) {
		connectDatabase($database, $outletDbName, $dbIPAddress, $dbLogin, $dbPassword);
		//get shop information
		$shopId = 0;
		$shopSQL = "SELECT shop_id FROM ".$outletDbName.".out_shops WHERE shop_code='".$shopCode."' and shop_status=''";
		$shopSQLResult = mysql_query($shopSQL);
		if ($shopRow = mysql_fetch_array($shopSQLResult))
			$shopId = $shopRow['shop_id'];
		
		if ($shopId == 0)
			return "s";
		
		//get outlet information
		$outletId = 0;
		$outletSQL = "SELECT olet_id FROM ".$outletDbName.".out_outlets WHERE olet_shop_id=".$shopId." and olet_code='".$outletCode."' and olet_status=''";
		$outletSQLResult = mysql_query($outletSQL);
		if ($outletRow = mysql_fetch_array($outletSQLResult)) {
			$outletId = $outletRow['olet_id'];
		}
		
		if ($outletId == 0)
			return "o";
		
		connectDatabase($database, $posDbName, $dbIPAddress, $dbLogin, $dbPassword);
		//get corresponding business day record
		$bdayId = 0;
		$bdayDate = substr($bday, 0, 4)."-".substr($bday, 4, 2)."-".substr($bday, 6, 2);
		$bdaySQL = "SELECT bday_id FROM ".$posDbName.".pos_business_days WHERE bday_date='".$bdayDate."' and bday_shop_id=".$shopId." and bday_olet_id=".$outletId." and bday_status<>'d'";
		$bdaySQLResult = mysql_query($bdaySQL);
		if ($bdayRow = mysql_fetch_array($bdaySQLResult)) 
			$bdayId = $bdayRow['bday_id'];
		
		$noData = false;
		if ($bdayId == 0) 
			$noData = true;
		
		// register the filter
		stream_filter_register('crlf', 'crlf_filter');
		
		//create a result csv file
		$resultFile = fopen($setup['ExportDir'].$sSalesDataFileName, 'w');
		if($resultFile === false)
			return "fail_to_create_result_file";
		
		// attach filter to output file
		stream_filter_append($resultFile, 'crlf');
		
		//get payment methods
		$paymentMethods = array();
		$paymentMethodSQL = "SELECT paym_id, paym_code FROM ".$posDbName.".pos_payment_methods WHERE paym_status=''";
		$paymentMethodSQLResult = mysql_query($paymentMethodSQL);
		while ($paymentMethodRow = mysql_fetch_array($paymentMethodSQLResult)) 
			$paymentMethods[$paymentMethodRow['paym_code']] = $paymentMethodRow['paym_id'];
		
		$paytypeSetup = array();
		foreach($paymentListType as $paymentListName) {
			for($i=1; $i<=50; $i++) {
				if(strcmp($setup[$paymentListName][$i], "") != 0)
					$paytypeSetup[$paymentListName][$i] = $paymentMethods[$setup[$paymentListName][$i]];
				else
					$paytypeSetup[$paymentListName][$i] = "";
			}
		}
		
		//get the check information base on bdayId
		$checkSQL = "SELECT * FROM ".$posDbName.".pos_checks WHERE chks_bday_id=".$bdayId." AND chks_shop_id=".$shopId." and chks_olet_id=".$outletId." and chks_non_revenue=''";
		$checkSQLResult = mysql_query($checkSQL);
		$checkCount = 0;
		while ($checkRow = mysql_fetch_array($checkSQLResult)) {
			
			//initialize the result set
			$salesData['MallCode'] = $setup['MallCode'];
			$salesData['TenantCode'] = $setup['TenantCode'];
			$salesData['TillNumber'] = 1;
			$salesData['SalesDate'] = $bdayDate."T00:00:00";
			$salesData['GrossSalesValue'] = 0;
			$salesData['GST'] = 0;
			$salesData['SalesMiscValue'] = 0;
			$salesData['ServiceCharge'] = 0;
			$salesData['NetSalesValue'] = 0;
			$salesData['ReceiptNumber'] = "";
			$salesData['ReserveColumn'] = 1;
			$salesData['TotalSoldQty'] = 0;
			$salesData['TotalDiscountQty'] = 0;
			$salesData['TotalDiscountValue'] = 0;
			$salesData['Cash'] = 0;
			$salesData['Nets'] = 0;
			$salesData['CreditCard'] = 0;
			$salesData['DebitCard'] = 0;
			$salesData['AsiaSquareVoucher'] = 0;
			$salesData['Cheque'] = 0;
			$salesData['OtherVouchers'] = 0;
			$salesData['Others'] = 0;
			$salesData['TotalItemisedVoidQty'] = 0;
			$salesData['TotalItemisedVoidValue'] = 0;
			$salesData['TotalTransactionVoidQty'] = 0;
			$salesData['TotalTransactionVoidValue'] = 0;
			$salesData['TotalValueForCatering'] = 0;
			
			if($checkRow['chks_status'] == "d") {
				//deleted check
				$salesData['ReceiptNumber'] = $checkRow['chks_check_prefix'].$checkRow['chks_check_num'];
				$salesData['TotalTransactionVoidQty'] = 1;
				$salesData['TotalTransactionVoidValue'] = $checkRow['chks_item_total'] + $checkRow['chks_sc1'] + $checkRow['chks_sc2'] + $checkRow['chks_sc3'] + $checkRow['chks_sc4'] + $checkRow['chks_sc5'] + $checkRow['chks_tax1'] + $checkRow['chks_tax2'] + $checkRow['chks_tax3'] + $checkRow['chks_tax4'] + $checkRow['chks_tax5'] + $checkRow['chks_tax6'] + $checkRow['chks_tax7'] + $checkRow['chks_tax8'] + $checkRow['chks_tax9'] + $checkRow['chks_tax10'] + $checkRow['chks_tax11'] + $checkRow['chks_tax12'] + $checkRow['chks_tax13'] + $checkRow['chks_tax14'] + $checkRow['chks_tax15'] + $checkRow['chks_tax16'] + $checkRow['chks_tax17'] + $checkRow['chks_tax18'] + $checkRow['chks_tax19'] + $checkRow['chks_tax20'] + $checkRow['chks_tax21'] + $checkRow['chks_tax22'] + $checkRow['chks_tax23'] + $checkRow['chks_tax24'] + $checkRow['chks_tax25'] + $checkRow['chks_round_amount'] + $checkRow['chks_pre_disc'] + $checkRow['chks_mid_disc'] + $checkRow['chks_post_disc'];
				
				//get the void check items
				$itemSQL = "SELECT * FROM ".$posDbName.".pos_check_items WHERE citm_bday_id=".$bdayId." and citm_shop_id=".$shopId." and citm_olet_id=".$outletId." and citm_chks_id=".$checkRow['chks_id'];
				$itemSQLResult = mysql_query($itemSQL);
				while($itemRow = mysql_fetch_array($itemSQLResult)) {
					$salesData['TotalItemisedVoidQty'] += $itemRow['citm_qty'];
					
					$itemDiscTotal = 0;
					$itemDiscSQL = "SELECT * FROM ".$posDbName.".pos_check_discounts WHERE cdis_bday_id=".$bdayId." and cdis_shop_id=".$shopId." and cdis_olet_id=".$outletId." and cdis_chks_id=".$checkRow['chks_id']." and cdis_citm_id=".$itemRow['citm_id'];
					$itemDiscSQLResult = mysql_query($itemDiscSQL);
					while($itemDiscRow = mysql_fetch_array($itemDiscSQLResult)) {
						$itemDiscTotal += $itemDiscRow['cdis_round_total'];
					}
					$salesData['TotalItemisedVoidValue'] += ($itemRow['citm_round_total'] + $itemDiscTotal);
				}
				
				fputcsv($resultFile, $salesData);
				$checkCount++;
				continue;
			}
			
			//$salesData['GrossSalesValue'] = $checkRow['chks_item_total'] + $checkRow['chks_sc1'] + $checkRow['chks_sc2'] + $checkRow['chks_sc3'] + $checkRow['chks_sc4'] + $checkRow['chks_sc5'] + $checkRow['chks_tax1'] + $checkRow['chks_tax2'] + $checkRow['chks_tax3'] + $checkRow['chks_tax4'] + $checkRow['chks_tax5'] + $checkRow['chks_tax6'] + $checkRow['chks_tax7'] + $checkRow['chks_tax8'] + $checkRow['chks_tax9'] + $checkRow['chks_tax10'] + $checkRow['chks_tax11'] + $checkRow['chks_tax12'] + $checkRow['chks_tax13'] + $checkRow['chks_tax14'] + $checkRow['chks_tax15'] + $checkRow['chks_tax16'] + $checkRow['chks_tax17'] + $checkRow['chks_tax18'] + $checkRow['chks_tax19'] + $checkRow['chks_tax20'] + $checkRow['chks_tax21'] + $checkRow['chks_tax22'] + $checkRow['chks_tax23'] + $checkRow['chks_tax24'] + $checkRow['chks_tax25'] + $checkRow['chks_pre_disc'] + $checkRow['chks_mid_disc'] + $checkRow['chks_post_disc'] + $checkRow['chks_round_amount'];
			$salesData['GrossSalesValue'] = $checkRow['chks_item_total'] + $checkRow['chks_sc1'] + $checkRow['chks_sc2'] + $checkRow['chks_sc3'] + $checkRow['chks_sc4'] + $checkRow['chks_sc5'] + $checkRow['chks_tax1'] + $checkRow['chks_tax2'] + $checkRow['chks_tax3'] + $checkRow['chks_tax4'] + $checkRow['chks_tax5'] + $checkRow['chks_tax6'] + $checkRow['chks_tax7'] + $checkRow['chks_tax8'] + $checkRow['chks_tax9'] + $checkRow['chks_tax10'] + $checkRow['chks_tax11'] + $checkRow['chks_tax12'] + $checkRow['chks_tax13'] + $checkRow['chks_tax14'] + $checkRow['chks_tax15'] + $checkRow['chks_tax16'] + $checkRow['chks_tax17'] + $checkRow['chks_tax18'] + $checkRow['chks_tax19'] + $checkRow['chks_tax20'] + $checkRow['chks_tax21'] + $checkRow['chks_tax22'] + $checkRow['chks_tax23'] + $checkRow['chks_tax24'] + $checkRow['chks_tax25'] + $checkRow['chks_round_amount'];
			$salesData['GST'] = $checkRow['chks_incl_tax_ref1'] + $checkRow['chks_incl_tax_ref2'] + $checkRow['chks_incl_tax_ref3'] + $checkRow['chks_incl_tax_ref4'];
			$salesData['SalesMiscValue'] = $checkRow['chks_round_amount'];
			$salesData['ServiceCharge'] = $checkRow['chks_sc1'] + $checkRow['chks_sc2'] + $checkRow['chks_sc3'] + $checkRow['chks_sc4'] + $checkRow['chks_sc5'];
			$salesData['ReceiptNumber'] = $checkRow['chks_check_prefix'].$checkRow['chks_check_num'];
			
			// check's items
			$itemDiscTotal = 0;
			$itemSQL = "SELECT * FROM ".$posDbName.".pos_check_items WHERE citm_bday_id=".$bdayId." and citm_shop_id=".$shopId." and citm_olet_id=".$outletId." and citm_chks_id=".$checkRow['chks_id'];
			$itemSQLResult = mysql_query($itemSQL);
			while ($itemRow = mysql_fetch_array($itemSQLResult)) {
				$salesData['TotalSoldQty'] += $itemRow['citm_qty'];
				
				// get item discount
				if($itemRow['citm_pre_disc'] != 0 || $itemRow['citm_mid_disc'] != 0 || $itemRow['citm_post_disc'] != 0) {
					$itemDiscSQL = "SELECT * FROM ".$posDbName.".pos_check_discounts WHERE cdis_bday_id=".$bdayId." and cdis_shop_id=".$shopId." and cdis_olet_id=".$outletId." and cdis_chks_id=".$checkRow['chks_id']." and cdis_citm_id=".$itemRow['citm_id']." and cdis_status=''";
					$itemDiscResult = mysql_query($itemDiscSQL);
					while ($itemDiscRow = mysql_fetch_array($itemDiscResult)) {
						$itemDiscTotal += $itemDiscRow['cdis_round_total'];
						$salesData['TotalDiscountQty'] ++;
						$salesData['TotalDiscountValue'] += $itemDiscRow['cdis_round_total'];
					}
				}
			}
			//$salesData['NetSalesValue'] = $checkRow['chks_item_total'] + $itemDiscTotal - ($checkRow['chks_incl_tax_ref1'] + $checkRow['chks_incl_tax_ref2'] + $checkRow['chks_incl_tax_ref3'] + $checkRow['chks_incl_tax_ref4']);
			$salesData['NetSalesValue'] = $checkRow['chks_item_total'] + $salesData['TotalDiscountValue'] - ($checkRow['chks_incl_tax_ref1'] + $checkRow['chks_incl_tax_ref2'] + $checkRow['chks_incl_tax_ref3'] + $checkRow['chks_incl_tax_ref4']);
			$salesData['TotalDiscountValue'] = ($salesData['TotalDiscountValue'] * -1);	//change the discount total value to positive
			
			// check's payments
			$paymentMethodSQL = "SELECT * FROM ".$posDbName.".pos_check_payments WHERE cpay_bday_id=".$bdayId." and cpay_shop_id=".$shopId." and cpay_shop_id=".$shopId." and cpay_olet_id=".$outletId." and cpay_chks_id=".$checkRow['chks_id']." and cpay_status=''";
			$paymentMethodSQLResult = mysql_query($paymentMethodSQL);
			while ($paymentMethodRow = mysql_fetch_array($paymentMethodSQLResult)) {
				$found = false;
				
				// cash paytype list
				foreach($paymentListType as $paymentListTypeName) {
					foreach($paytypeSetup[$paymentListTypeName] as $key => $paytypeId) {
						if(strcmp($paytypeId, "") == 0)
							continue;
						
						if(strcmp($paytypeId, $paymentMethodRow['cpay_paym_id']) != 0)
							continue;
						
						switch($paymentListTypeName) {
							case 'CashPaytypeList':
								$found = true;
								$salesData['Cash'] += $paymentMethodRow['cpay_pay_total'];
								break;
							case 'NetsPaytypeList':
								$found = true;
								$salesData['Nets'] += $paymentMethodRow['cpay_pay_total'];
								break;
							case 'CreditCardPaytypeList':
								$found = true;
								$salesData['CreditCard'] += $paymentMethodRow['cpay_pay_total'];
								break;
							case 'DebitCardPaytypeList':
								$found = true;
								$salesData['DebitCard'] += $paymentMethodRow['cpay_pay_total'];
								break;
							case 'AsiaSquareVoucherPaytypeList':
								$found = true;
								$salesData['AsiaSquareVoucher'] += $paymentMethodRow['cpay_pay_total'];
								break;
							case 'ChequePaytypeList':
								$found = true;
								$salesData['Cheque'] += $paymentMethodRow['cpay_pay_total'];
								break;
							case 'OtherVoucherPaytypeList':
								$found = true;
								$salesData['OtherVouchers'] += $paymentMethodRow['cpay_pay_total'];
								break;
						}
					}
				}
				
				if($found == false)
					$salesData['Others'] += $paymentMethodRow['cpay_pay_total'];
			}
			
			fputcsv($resultFile, $salesData);
			$checkCount++;
		}
		
		if($noData || $checkCount == 0) {
			$salesData['MallCode'] = $setup['MallCode'];
			$salesData['TenantCode'] = $setup['TenantCode'];
			$salesData['TillNumber'] = 1;
			$salesData['SalesDate'] = $bdayDate."T00:00:00";
			$salesData['GrossSalesValue'] = 0;
			$salesData['GST'] = 0;
			$salesData['SalesMiscValue'] = 0;
			$salesData['ServiceCharge'] = 0;
			$salesData['NetSalesValue'] = 0;
			$salesData['ReceiptNumber'] = "";
			$salesData['ReserveColumn'] = 1;
			$salesData['TotalSoldQty'] = 0;
			$salesData['TotalDiscountQty'] = 0;
			$salesData['TotalDiscountValue'] = 0;
			$salesData['Cash'] = 0;
			$salesData['Nets'] = 0;
			$salesData['CreditCard'] = 0;
			$salesData['DebitCard'] = 0;
			$salesData['AsiaSquareVoucher'] = 0;
			$salesData['Cheque'] = 0;
			$salesData['OtherVouchers'] = 0;
			$salesData['Others'] = 0;
			$salesData['TotalItemisedVoidQty'] = 0;
			$salesData['TotalItemisedVoidValue'] = 0;
			$salesData['TotalTransactionVoidQty'] = 0;
			$salesData['TotalTransactionVoidValue'] = 0;
			$salesData['TotalValueForCatering'] = 0;
			
			fputcsv($resultFile, $salesData);
		}
		
		fclose($resultFile);
		
		if($noData || $checkCount == 0)
			return "no_data";
		else
			return "";
	}
	
	function writeLog($error, $message) {
		$currentMonth = date('m');
		$logFileName = "salesDataGen.".$currentMonth;
		
		$logFile = fopen($logFileName, "a+");
		if($error)
			$logContent = date('Y-m-d H:i:s')." - ERROR - ".$message."\r\n";
		else
			$logContent = date('Y-m-d H:i:s')." - ".$message."\r\n";
		fputs($logFile, $logContent);
		fclose($logFile);
	}
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Minor function
	// filter class that applies CRLF line endings
	class crlf_filter extends php_user_filter
	{
		function filter($in, $out, &$consumed, $closing)
		{
			while ($bucket = stream_bucket_make_writeable($in)) {
				// make sure the line endings aren't already CRLF
				$bucket->data = preg_replace("/(?<!\r)\n/", "\r\n", $bucket->data);
				$consumed += $bucket->datalen;
				stream_bucket_append($out, $bucket);
			}
			return PSFS_PASS_ON;
		}
	}
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//main program
	$error = 0;
	$database = null;
	$paymentListType = array("CashPaytypeList", "NetsPaytypeList", "CreditCardPaytypeList", "DebitCardPaytypeList", "AsiaSquareVoucherPaytypeList", "ChequePaytypeList", "OtherVoucherPaytypeList");
	
	$setup = readSetup();
	if($setup == false) {
		writeLog(true, "Missing setup file");
		return;
	}
	
	/***** read outlet data *****/
	if(!isset($setup['ShopCode'])) {
		writeLog(true, "Missing 'ShopCode' in setup file");
		$error = 1;
	}else if(strcmp($setup['ShopCode'], "") == 0) {
		writeLog(true, "Empty setup for ShopCode");
		$error = 1;
	}
	
	if(!isset($setup['OutletCode'])) {
		writeLog(true, "Missing 'OutletCode' in setup file");
		$error = 1;
	}else if(strcmp($setup['OutletCode'], "") == 0) {
		writeLog(true, "Empty setup for OutletCode");
		$error = 1;
	}
	
	/***** read database connection data *****/
	if(!isset($setup['PosDatabaseName'])) {
		writeLog(true, "Missing 'PosDatabaseName' in setup file");
		$error = 1;
	}else if(strcmp($setup['PosDatabaseName'], "") == 0) {
		writeLog(true, "Empty setup for PosDatabaseName");
		$error = 1;
	}
	
	if(!isset($setup['OutletDatabaseName'])) {
		writeLog(true, "Missing 'OutletDatabaseName' in setup file");
		$error = 1;
	}else if(strcmp($setup['OutletDatabaseName'], "") == 0) {
		writeLog(true, "Empty setup for OutletDatabaseName");
		$error = 1;
	}
	
	if(!isset($setup['DBIPAddress'])) {
		writeLog(true, "Missing 'DBIPAddress' in setup file");
		$error = 1;
	}else if(strcmp($setup['DBIPAddress'], "") == 0) {
		writeLog(true, "Empty setup for DBIPAddress");
		$error = 1;
	}
	
	if(!isset($setup['DBLogin'])) {
		writeLog(true, "Missing 'DBLogin' in setup file");
		$error = 1;
	}else if(strcmp($setup['DBLogin'], "") == 0) {
		writeLog(true, "Empty setup for DBLogin");
		$error = 1;
	}
	
	if(!isset($setup['DBPassword'])) {
		writeLog(true, "Missing 'DBPassword' in setup file");
		$error = 1;
	}else if(strcmp($setup['DBPassword'], "") == 0) {
		writeLog(true, "Empty setup for DBPassword");
		$error = 1;
	}
		
	/***** read program setup data *****/
	if(!isset($setup['ExportDir'])) {
		writeLog(true, "Missing 'ExprotDir' in setup file");
		$error = 1;
	}else if(strcmp($setup['ExportDir'], "") == 0) {
		writeLog(true, "Empty setup for ExportDir");
		$error = 1;
	}else {
		$length = strlen($setup['ExportDir']);
		if(strcmp(substr($setup['ExportDir'], ($length-1), 1), "\\") != 0)
			$setup['ExportDir'] = $setup['ExportDir']."\\";
	}
	
	if(!isset($setup['MallCode'])) {
		writeLog(true, "Missing 'MallCode' in setup file");
		$error = 1;
	}
	
	if(!isset($setup['TenantCode'])) {
		writeLog(true, "Missing 'TenantCode' in setup file");
		$error = 1;
	}
	
	/***** read FTP connection *****/
	$needFTPConnection = false;
	if(isset($setup['NeedFTPConnection']) && $setup['NeedFTPConnection'] == 1) {
		if(!isset($setup['FTPHost'])) {
			writeLog(true, "Missing 'FTPHost' in setup file");
			$error = 1;
		}else if(strcmp($setup['FTPHost'], "") == 0) {
			writeLog(true, "Empty setup for FTPHost");
			$error = 1;
		}
		
		if(!isset($setup['FTPLogin'])) {
			writeLog(true, "Missing 'FTPLogin' in setup file");
			$error = 1;
		}else if(strcmp($setup['FTPLogin'], "") == 0) {
			writeLog(true, "Empty set for FTPLogin");
			$error = 1;
		}
		
		if(!isset($setup['FTPPassword'])) {
			writeLog(true, "Missing 'FTPPassword' in setup file");
			$error = 1;
		}else if(strcmp($setup['FTPPassword'], "") == 0) {
			writeLog(true, "Empty setup for FTPPassword");
			$error = 1;
		}
		
		$ftpTargetPath = "";
		if(isset($setup['FTPTargetPath']))
			$ftpTargetPath = $setup['FTPTargetPath'];
		
		$needFTPConnection = true;
	}
	
	foreach($paymentListType as $key => $paymentTypeListName) {
		if(!isset($setup[$paymentTypeListName])) {
			writeLog(true, "Missing '".$paymentTypeListName."' in setup file");
			$error = 1;
		}else {
			$paymentTypeList = $setup[$paymentTypeListName];
			unset($setup[$paymentTypeListName]);
			
			for($i=1; $i<=50; $i++)
				$setup[$paymentTypeListName][$i] = "";
			
			$listCnt = 1;
			$tok = strtok($paymentTypeList, ",");
			while ($tok !== false) {
				$setup[$paymentTypeListName][$listCnt] = $tok;
				$listCnt++;
				$tok = strtok(",");
			}
		}
	}
	
	if($error)
		return;
	
	//testing connect of database
	$testingDB = null;
	if (connectDatabase($testingDB, $setup['PosDatabaseName'], $setup['DBIPAddress'], $setup['DBLogin'], $setup['DBPassword']) == false) {
		writeLog(true, mysql_error());
		return;
	}
	$testingDB = null;
	if (connectDatabase($testingDB, $setup['OutletDatabaseName'], $setup['DBIPAddress'], $setup['DBLogin'], $setup['DBPassword']) == false) {
		writeLog(true, mysql_error());
		return;
	}
	
	//get business date
	$fromScreen = false;
	$dBusinessDate = "";
	if(!isset($_GET['BusinessDate']) && !isset($_POST['BusinessDate']))
		$dBusinessDate = date('Ymd', mktime(0, 0, 0, date("m")  , date("d")-1, date("Y")));
	else {
		$fromScreen = true;
		if(isset($_POST['BusinessDate']))
			$dBusinessDate = $_POST['BusinessDate'];
		else
			$dBusinessDate = $_GET['BuinessDate'];
		if (strlen($dBusinessDate) != 8)
			echo "Invalid Business Date<br>";
		if (intval(substr($dBusinessDate, 4, 2)) <= 0  || intval(substr($dBusinessDate, 4, 2)) > 13)
			echo "Invalid Business Date<br>";
		if (intval(substr($dBusinessDate, 6, 2)) > 31)
			echo "Invalid Business Date<br>";
	}
	
	if(strcmp($setup['ExportDir'], "") != 0 && !file_exists($setup['ExportDir'])) {
		mkdir($setup['ExportDir'], 0777);
		/*if($fromScreen)
			echo "Export directory is not exist";
		writeLog(true, "Export directory is not exist");
		return;*/
	}
	
	//generate output file name
	$sSalesDataFileName = $setup['MallCode'].$setup['TenantCode'].$dBusinessDate.".csv";
	
	$sResult = getSalesAmount($database, $setup['PosDatabaseName'], $setup['OutletDatabaseName'], $setup['DBIPAddress'], $setup['DBLogin'], $setup['DBPassword'], $dBusinessDate, $setup['ShopCode'], $setup['OutletCode'], $setup, $sSalesDataFileName, $paymentListType);
	
	if(strcmp($sResult, "s") == 0) {
		if($fromScreen)
			echo "Wrong shop code<br>";
		writeLog(true, "Wrong shop code");
		return;
	}else if(strcmp($sResult, "o") == 0) {
		if($fromScreen)
			echo "Wrong outlet code<br>";
		writeLog(true, "Wrong outlet code");
		return;
	}else if(strcmp($sResult, "no_data") == 0) {
		if($fromScreen)
			echo "No data";
		writeLog(false, "No data");
	}else if(strcmp($sResult, "fail_to_create_result_file") == 0) {
		if($fromScreen)
			echo "Fail to create result file";
		writeLog(true, "Fail to create result file");
		return;
	}
	
	writeLog(false, "Outlet file: ".$sSalesDataFileName." has been generated");
	
	/********************************************/
	/***** Transfer output file through FTP *****/
	/********************************************/
	if($needFTPConnection) {
		//testing connection of ftp
		$ftpConnection = ftp_connect($setup['FTPHost'], 21, 3);
		if (!$ftpConnection) {
			writeLog(true, "Cannot connect to FTP server:".$setup['FTPHost']);
			return;
		}
		ftp_close($ftpConnection);
		
		//check folder existence
		//if(!file_exists($setup['FileSentFolder']))
		//	mkdir($setup['FileSentFolder']);
		
		$ftpConnection = ftp_connect($setup['FTPHost'], 21, 15);
		if ($ftpConnection) {
			writeLog(false, "Connect to FTP server:".$setup['FTPHost']);
			if (ftp_login($ftpConnection, $setup['FTPLogin'], $setup['FTPPassword']) == FALSE) {
				if($fromScreen)
					echo "Fail to login to FTP Server";
				writeLog(true, "Fail to login to FTP Server");
				ftp_close($ftpConnection);
				return;
			}
			
			ftp_pasv($ftpConnection, true);
			$currentFTPPath = ftp_pwd($ftpConnection);
			if (strcmp($ftpTargetPath, "") != 0)
				ftp_chdir($ftpConnection, $ftpTargetPath);
		
		
			/*$files = scandir($setup['SalesDataOutputFolder']);
			foreach($files as $fileName) {
				if(strcmp($fileName, ".") == 0 || strcmp($fileName, "..") == 0)
					continue;
				
				//if(ftp_put($ftpConnection, $setup['SalesDataOutputFolder'].$sSalesDataFileName, $sSalesDataFileName, FTP_BINARY)) {
				if(ftp_put($ftpConnection, $fileName, $setup['SalesDataOutputFolder'].$fileName, FTP_BINARY)) {
					if($fromScreen)
						echo "Success to upload file to FTP server<br>";
					writeLog(false, "Success to upload file(".$fileName.") to FTP server");
					rename($setup['SalesDataOutputFolder'].$fileName, $setup['FileSentFolder'].$fileName);
				}else {
					if($fromScreen)
						echo "Fail to upload file to FTP server<br>";
					writeLog(true, "Fail to update file(".$fileName.") to FTP server");
				}
			}*/
			if(ftp_put($ftpConnection, $sSalesDataFileName, $setup['ExportDir'].$sSalesDataFileName, FTP_BINARY)) {
				if($fromScreen)
					echo "Success to upload file to FTP server<br>";
				writeLog(false, "Success to upload file(".$sSalesDataFileName.") to FTP server");
			}else {
				if($formScreen)
					echo "Fail to upload to FTP server<br>";
				writeLog(true, "Fail to update file(".$sSalesDataFileName.") to  FTP server");
			}
			
			ftp_close($ftpConnection);
			if($fromScreen)
				echo "<a href='salesData.php'>Back</a>";
		}else {
			if($fromScreen)
				echo "<a href='salesData.php'>Back</a>";
			writeLog(true, "Cannot connect to FTP server:".$setup['FTPHost']);
			return;
		}
	}else {
		if($fromScreen)
			echo "<a href='salesData.php'>Back</a>";
	}
?>