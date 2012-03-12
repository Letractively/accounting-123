<?php

require ("../settings.php");
require ("../core-settings.php");
require ("../pdf-settings.php");

quoteDetails($_GET);

function quoteDetails($_GET)
{

	extract ($_GET);
	global $set_mainFont;

	$showvat = TRUE;

	$pdf = &new Cezpdf;
	$pdf->selectFont($set_mainFont);

	// Validate
	require_lib("validate");
	$v = new Validate();
	$v->isOk($quoid, "num", 1, 20, "Invalid invoice number.");

	// Any errors?
	if ($v->isError()) {
		$err = "";
		$errors = $v->getErrors();
		foreach ($errors as $e) {
			$err .= "<li class='err'>$e[msg]</li>";
		}
		$OUTPUT = $confirm;
		require("../template.php");
	}



	// Invoice info
	db_conn("cubit");
	$sql = "SELECT *,cordno as invnum FROM quotes WHERE quoid='$quoid' AND DIV='".USER_DIV."'";
	$invRslt = db_exec($sql) or errDie("Unable to retrieve invoice info.");
	if (pg_num_rows($invRslt) < 1) {
		return "<li class='err'>Not found</li>";
	}
	$inv = pg_fetch_array($invRslt);

	db_conn("cubit");
	$sql = "SELECT symbol FROM currency WHERE fcid='$inv[fcid]'";
	$curRslt = db_exec($sql) or errDie("Unable to retrieve currency from Cubit.");
	$curr = pg_fetch_result($curRslt, 0);
	if (!$curr) $curr = CUR;

	// Check if stock was selected
	db_conn("cubit");
	$sql = "SELECT stkid FROM quote_items WHERE quoid='$quoid' AND DIV='".USER_DIV."'";
	$cRslt = db_exec($sql) or errDie("Unable to retrieve invoice info.");
	if (pg_num_rows($cRslt) < 1) {
		$error = "<li class='err'>Invoice number <b>$quoid</b> has no items</li>";
		$OUTPUT = $error;
	}

	// Only needs to be blank, we're manually adding text
	$heading = array ( array("") );

	// Company info ----------------------------------------------------------
	db_conn("cubit");
	$sql = "SELECT * FROM compinfo WHERE div='".USER_DIV."'";
	$ciRslt = db_exec($sql) or errDie("Unable to retrieve company info from Cubit.");
	$comp = pg_fetch_array($ciRslt);

	// Banking information ---------------------------------------------------
	$bnkData = qryBankAcct(getdSetting("BANK_DET"));

	$compinfo = array();
	$compinfo[] = array ($comp["addr1"], $comp["paddr1"]);
	$compinfo[] = array (pdf_lstr($comp["addr2"], 35), pdf_lstr($comp["paddr2"], 35));
	$compinfo[] = array (pdf_lstr($comp["addr3"], 35), pdf_lstr($comp["paddr3"], 35));
	$compinfo[] = array (pdf_lstr($comp["addr4"], 35), "$comp[postcode]");
	$compinfo[] = array ("<b>REG: </b>$comp[regnum]", "<b>$bnkData[bankname]</b>");
	$compinfo[] = array ("<b>VAT REG: </b>$comp[vatnum]", "<b>Branch: </b>$bnkData[branchname]");
	$compinfo[] = array ("<b>Tel:</b> $comp[tel]", "<b>Branch Code: </b>$bnkData[branchcode]");
	$compinfo[] = array ("<b>Fax:</b> $comp[fax]", "<b>Acc Num: </b>$bnkData[accnum]");

	// Date ------------------------------------------------------------------
	$date = array (
		array ("<b>Date</b>"),
		array ($inv['odate'])
	);
	// Document info ---------------------------------------------------------
	db_conn('cubit');
	$Sl="SELECT * FROM settings WHERE constant='SALES'";
	$Ri=db_exec($Sl) or errDie("Unable to get settings.");

	$data=pg_fetch_array($Ri);

	db_conn('cubit');

	$Sl="SELECT * FROM settings WHERE constant='SALES'";
	$Ri=db_exec($Sl) or errDie("Unable to get settings.");

	$data=pg_fetch_array($Ri);

	if($data['value']=="Yes") {
		$sp="<b>Sales Person: </b>$inv[salespn]";
	} else {
		$sp="";
	}

	if(!isset($inv['docref']))
		$inv['docref'] = "";

	$docinfo = array (
		array ("<b>Quote No:</b> $inv[quoid]"),
		array ("<b>Proforma Inv No:</b> $inv[docref]"),
		array ("<b>Sales Req No:</b> $inv[invnum]"),
		array ("$sp")
	);
	if (isset($salespn)) {
		$docinfo[] = array("<b>Sales Person:</b> $salespn");
	}

	// Retrieve the customer information -------------------------------------
	db_conn("cubit");
	$sql = "SELECT * FROM customers WHERE cusnum='$inv[cusnum]'";
	$cusRslt = db_exec($sql) or errDie("Unable to retrieve customer information from Cubit.");
	$cusData = pg_fetch_array($cusRslt);

	// Customer info ---------------------------------------------------------
	$invoice_to = array(
		array ("")
	);

	$cusinfo = array (
		array ("<b>$inv[surname]</b>")
	);

	$cusaddr = explode("\n", $cusData['addr1']);
	foreach ( $cusaddr as $v ) {
		$cusinfo[] = array(pdf_lstr($v, 40));
	}

	$cusinfo[] = array("<b>Account no: </b>$cusData[accno]");

	$cuspaddr = array (
		array("<b>Postal Address</b>"),
	);

	$paddr = explode("\n", $cusData["paddr1"]);
	foreach ($paddr as $addr) {
		$cuspaddr[] = array($addr);
	}

	$cusdaddr = array (
		array ("<b>Delivery Address:</b>"),
	);

	if(!isset($inv['branch']))
		$inv['branch'] = "0";

	if($inv['branch'] == 0){
			$branchname = "Head Office";
			$cusaddr = explode("\n", $cusData['addr1']);
	}else {
			$get_addr = "SELECT * FROM customer_branches WHERE id = '$inv[branch]' LIMIT 1";
			$run_addr = db_exec($get_addr);
			if (pg_numrows($run_addr) < 1) {
					$cusaddr = Array ();
					$branchname = "Head Office";
			} else {
					$barr = pg_fetch_array($run_addr);
					$cusaddr = explode("\n", $barr['branch_descrip']);
					$branchname = $barr['branch_name'];
			}
	}

	if(!isset($inv["del_addr"]))
		$inv["del_addr"] = "";

	$cusdaddr[] = array(pdf_lstr("Branch : $branchname", 30));
	$del_addr = explode("\n", $inv["del_addr"]);
	foreach ($del_addr as $addr ) {
		$cusdaddr[] = array(pdf_lstr($addr, 30));
	}

	// Registration numbers --------------------------------------------------
	$regnos = array (
		array (
			"<b>VAT No:</b>",
			"<b>Order No:</b>"
		),
		array (
			"$inv[cusvatno]",
			"$inv[ordno]"
		)
	);

	// Items display ---------------------------------------------------------
	$items = array ();

	db_conn("cubit");
	$sql = "SELECT * FROM quote_items WHERE quoid='$quoid' AND DIV='".USER_DIV."'";
	$stkdRslt = db_exec($sql);

	while ($stkd = pg_fetch_array($stkdRslt)) {
		// Get warehouse
		db_conn("exten");
		$sql = "SELECT * FROM warehouses WHERE whid='$stkd[whid]' AND DIV='".USER_DIV."'";
		$whRslt = db_exec($sql);
		$wh = pg_fetch_array($whRslt);

		// Get stock in this warehouse
		db_conn("cubit");
		$sql = "SELECT * FROM stock WHERE stkid='$stkd[stkid]' AND DIV='".USER_DIV."'";
		$stkRslt = db_exec($sql);
		$stk = pg_fetch_array($stkRslt);

		$sp = "";

		// Check Tax Excempt
		db_conn("cubit");
		$sql = "SELECT zero FROM vatcodes WHERE id='$stkd[vatcode]'";
		$zRslt = db_exec($sql) or errDie("Unable to retrieve vat code from Cubit.");
		$vatex = pg_fetch_result($zRslt, 0);

		if($vatex == "Yes"){
			$ex = "#";
		} else {
			$ex = "";
		}

		$sql = "SELECT * FROM vatcodes WHERE id='$stkd[vatcode]'";
		$runsql = db_exec($sql) or errDie("Unable to retrieve vat code from Cubit.");
		if(pg_numrows($runsql) < 1){
			return "Invalid VAT code entered";
		}

		$vd = pg_fetch_array($runsql);

		if((TAX_VAT != $vd['vat_amount']) AND ($vd['vat_amount'] != "0.00")){
			$showvat = FALSE;
		}

		// keep track of discounts
		//$disc += $stkd['disc'];
		if ($stkd["account"] > 0) {
			$description = $stkd["description"];
		} else {
			$description = $stk["stkdes"];
		}

		// Remove any new lines from the description
		$ar_desc = explode("\n", $description);
		$description = implode(" ", $ar_desc);

		$items[] = array(
			"Code"=>makewidth($pdf, 75, 12, $stk['stkcod']),
			"Description"=>makewidth($pdf, 175, 12, $ex.$description),
			"Qty"=>$stkd['qty'],
			"Unit Price"=>$curr.$stkd['unitcost'],
			"Unit Discount"=>$curr.$stkd['disc'],
			"Amount"=>$curr.$stkd['amt']
		);
	}

	$inv["comm"] = fixparag(&$pdf, 3, 520, 11, $inv["comm"]);
	/*$inv["comm"] = preg_replace("/[\n]/", " ", $inv["comm"]);

	$lines = array();
	$txtleft = $inv["comm"];
	$done = false;
	while (count($lines) < 3 && !$done) {
		$mc = maxwidth(&$pdf, 520, 11, $txtleft);

		// run until end of a word.
		while ($txtleft[$mc - 1] != ' ' && $mc < strlen($txtleft)) ++$mc;

		if ($mc == strlen($txtleft)) {
			$done = true;
		}

		$lines[] = substr($txtleft, 0, $mc);
		$txtleft = substr($txtleft, $mc);
	}

	if (strlen($txtleft) > 0) {
		$lines[2] .= "...";
	}

	$inv["comm"] = preg_replace("/  /", " ", implode("\n", $lines));*/

	// Comment ---------------------------------------------------------------
	$comment = array (
		array ("<i>VAT Exempt Indicator : #</i>"),
		array ($inv["comm"])
	);

	// Box for signature -----------------------------------------------------
	$sign = array (
		array ("<b>Terms:</b> $inv[terms] days"),
		array (''),
		array ("<b>Received in good order by:</b> ____________________"),
		array (''),
		// We aren't using a monospace font, so just a lot of spaces until it is aligned nicely.
		array ("                                      <b>Date:</b> ____________________")
	);

	// Totals ----------------------------------------------------------------

	if (!isset($showvat))
		$showvat = TRUE;

	if($showvat == TRUE){
		$vat14 = AT14;
	}else {
		$vat14 = "";
	}

	$totals = array (
		array ("1"=>"<b>Subtotal:</b> ", "2"=>$curr."$inv[subtot]"),
		array ("1"=>"<b>Trade Discount:</b> ", "2"=>$curr."$inv[discount]"),
		array ("1"=>"<b>Delivery Charge:</b> ", "2"=>$curr."$inv[delivery]"),
		array ("1"=>"<b>VAT $vat14:</b> ", "2"=>$curr."$inv[vat]"),
		array ("1"=>"<b>Total Incl VAT:</b> ", "2"=>$curr."$inv[total]")
	);
	$totCols = array (
		"1"=>array("width"=>90),
		"2"=>array("justification"=>"right")
	);

	$ic = 0;
	while ( ++$ic * 22 < count($items) );

	// Draw the pages, determine by the amount of items how many pages
	// if items > 20 start a new page
	$items_print = Array ();
	for ($i = 0; $i < $ic; $i++) {
		if ( $i ) $pdf->ezNewPage();

		// Page number -------------------------------------------------------
		$pagenr = array (
			array ("<b>Page number</b>"),
			array ($i + 1)
		);

		// Heading
		$heading_pos = drawTable(&$pdf, $heading, 0, 0, 520, 5);
		drawText(&$pdf, "<b>$comp[compname]</b>", 18, 0, ($heading_pos['y']/2)+6);
		drawText(&$pdf, "<b>Quote</b>", 20, $heading_pos['x']-120, ($heading_pos['y']/2)+9);

		// Should we display reprint on the invoice
		if (isset($type) AND ($type == "invreprint")) {
			drawText(&$pdf, "<b>Reprint</b>", 12, $heading_pos['x']-70, ($heading_pos['y']/2)+22);
		}

		$compinfo_pos = drawTable(&$pdf, $compinfo, 0, $heading_pos['y'], 320, 8);
		$date_pos = drawTable(&$pdf, $date, $compinfo_pos['x'], $heading_pos['y'] , 100, 3);
		$pagenr_pos = drawTable(&$pdf, $pagenr, $date_pos['x'], $heading_pos['y'], 100, 3);
		$docinfo_pos = drawTable(&$pdf, $docinfo, $compinfo_pos['x'], $date_pos['y'], 200, 5);
		$invoice_to_pos = drawTable(&$pdf, $invoice_to, 0, $compinfo_pos['y'], 520, 2);
		drawText(&$pdf, "<b>Tax Invoice to:</b>", 12, (520/2)-45, $invoice_to_pos['y']-7);

		$cusinfo_pos = drawTable(&$pdf, $cusinfo, 0, $invoice_to_pos['y'], 173, 8);
		$cuspaddr_pos = drawTable(&$pdf, $cuspaddr, $cusinfo_pos['x'], $invoice_to_pos['y'], 173, 8);
		$cusdaddr_pos = drawTable(&$pdf, $cusdaddr, $cuspaddr_pos['x'], $invoice_to_pos['y'], 174, 8);
		$regnos_pos = drawTable(&$pdf, $regnos, 0, $cusinfo_pos['y'], 520, 2);

		$items_start = ($i * 22);

		if ($i) $items_start++;

		if ($items_start >= (count($items) - 22)) {
			$items_end = count($items) - 1;
		} else {
			$items_end = ($i + 1) * 22;
		}
		$items_print = array();

		for ($j = $items_start; $j <= $items_end; $j++) {
			$items_print[$j] = $items[$j];
		}

		$cols = array(
			"Code"=>array("width"=>80),
			"Description"=>array("width"=>180),
			"Qty"=>array("width"=>33),
			"Unit Price"=>array("width"=>80, "justification"=>"right"),
			"Unit Discount"=>array("width"=>67, "justification"=>"right"),
			"Amount"=>array("width"=>80, "justification"=>"right")
		);

		$items_pos = drawTable(&$pdf, $items_print, 0, $regnos_pos['y']+2, 520, 22, $cols, 1);
		$comment_pos = drawTable(&$pdf, $comment, 0, $items_pos['y'], 520, 2);
		$sign_pos = drawTable(&$pdf, $sign, 0, $comment_pos['y'], 320, 5);
		$totals_pos = drawTable(&$pdf, $totals, $sign_pos['x'], $comment_pos['y'], 200, 5, $totCols);

		$pdf->addText(20,34,6,'Cubit Accounting');
	}
	$pdf->ezStream();

}
