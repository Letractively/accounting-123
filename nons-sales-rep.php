<?

#This program is copyright by Andre Coetzee email: ac@main.me
#and is licensed under the GPL v3
#
#
#
#
#Please add yourself to: http://www.accounting-123.com
#Developers, Software Vendors, Support, Accountants, Users
#
#
#The full software license can be found here:
#http://www.accounting-123.com/a.php?a=153/GPLv3
#
#
#
#
#
#
#
#
#
#
#

require ("settings.php");
require ("libs/ext.lib.php");

if (isset($_GET["stkid"])) {
	$OUTPUT = details($_GET);
}else{
	if (isset($_POST["key"])) {
		switch ($_POST["key"]) {
			case "view":
				$OUTPUT = printStk($_POST);
				break;
			case "export":
				$OUTPUT = export($_POST);
				break;
			case "report":
				$OUTPUT = report($_POST);
				break;
			default:
				$OUTPUT = slct();
				break;
		}
	} else {
			# Display default output
			$OUTPUT = slct();
	}
}

require ("template.php");




# Default view
function slct()
{
	//layout
	$view = "
				<P><P>
				<form action='".SELF."' method='POST' name='form'>
				<table ".TMPL_tblDflts.">
					<input type='hidden' name='key' value='view'>
					<tr>
						<th colspan='2'>Stock Sales Report</th>
					</tr>
					<tr bgcolor='".bgcolorg()."'>
						<td align='center' colspan='2'>
							".mkDateSelect("from",date("Y"),date("m"),"01")."
							&nbsp;&nbsp;&nbsp; TO &nbsp;&nbsp;&nbsp;
							".mkDateSelect("to")."
						</td>
					</tr>
					".TBL_BR."
					<tr bgcolor='".bgcolorg()."'>
						<td align='center' colspan='2'><input type='submit' value='View'></td>
					</tr>
				</table>
				</form>
				<p>
				<table border='0' cellpadding='2' cellspacing='1' width='15%'>
					<tr>
						<th>Quick Links</th>
					</tr>
					<tr bgcolor='".bgcolorg()."'>
						<td><a href='sales-reports.php'>Sales Reports</a></td>
					</tr>
					<tr bgcolor='".bgcolorg()."'>
						<td><a href='main.php'>Main Menu</a></td>
					</tr>
				</table>";
	return $view;

}



# show stock
function printStk ($_POST)
{
	
	# get vars
	extract ($_POST);

	# validate input
	require_lib("validate");
	$v = new  validate ();
	$v->isOk ($from_day, "num", 1,2, "Invalid from Date day.");
	$v->isOk ($from_month, "num", 1,2, "Invalid from Date month.");
	$v->isOk ($from_year, "num", 1,4, "Invalid from Date Year.");
	$v->isOk ($to_day, "num", 1,2, "Invalid to Date day.");
	$v->isOk ($to_month, "num", 1,2, "Invalid to Date month.");
	$v->isOk ($to_year, "num", 1,4, "Invalid to Date Year.");

	# mix dates
	$fromdate = $from_year."-".$from_month."-".$from_day;
	$todate = $to_year."-".$to_month."-".$to_day;

	if(!checkdate($from_month, $from_day, $from_year)){
			$v->isOk ($fromdate, "num", 1, 1, "Invalid from date.");
	}
	if(!checkdate($to_month, $to_day, $to_year)){
			$v->isOk ($todate, "num", 1, 1, "Invalid to date.");
	}

	# display errors, if any
	if ($v->isError ()) {
		$confirm = "";
		$errors = $v->getErrors();
		foreach ($errors as $e) {
			$confirm .= "<li class='err'>-".$e["msg"]."</li>";
		}
        return $confirm;
	}



	# connect to database
	db_connect ();

	// Layout
	$report = "
				<h3>Stock Sales Report</h3>
				<table ".TMPL_tblDflts.">
					<tr>
						<td colspan='5'><h3>Non-Stock Sales</h3></td>
					</tr>
					<tr>
						<th>Invoice no.</th>
						<th>Date</th>
						<th>VAT</th>
						<th>SubTotal</th>
						<th>Total</th>
					</tr>";

	# Get all relevant records
	db_connect();
	$sql = "SELECT * FROM salesrec WHERE edate >= '$fromdate' AND edate <= '$todate' AND typ = 'non' AND div = '".USER_DIV."'";
	$recRslt = db_exec($sql) or errDie("Unable to access databse.", SELF);

	$i = 0;
	$tot = 0;
	$totvat = 0;
	$totexc = 0;
	while ($rec = pg_fetch_array ($recRslt)) {

		# Calculate profit
		$tot += $rec['total'];
		$totvat += $rec['vat'];
		$excvat = sprint($rec['total'] - $rec['vat']);
		$totexc += $excvat;

		$report .= "
						<tr bgcolor='".bgcolorg()."'>
							<td>$rec[invnum]</td>
							<td>$rec[edate]</td>
							<td>".CUR." $rec[vat]</td>
							<td>".CUR." $excvat</td>
							<td>".CUR." $rec[total]</td>
						</tr>";
		$i++;
	}

	$tot = sprint($tot);
	$totvat = sprint($totvat);
	$totexc = sprint($totexc);

	$report .= "
					<tr bgcolor='".bgcolorg()."'>
						<td colspan='2'><b>Total Non-Stock Sales</b></td>
						<td>".CUR." $totvat</td>
						<td>".CUR." $totexc</td>
						<td>".CUR." $tot</td>
					</tr>
				</table>
			    <p>
				<table ".TMPL_tblDflts.">
					<tr>
						<td colspan='5'><h3>Non-Stock Credit Notes</h3></td>
					</tr>
					<tr>
						<th>Credit Note no.</th>
						<th>Date</th>
						<th>VAT</th>
						<th>SubTotal</th>
						<th>Total</th>
					</tr>";

	# Get all relevant records
	db_connect();
	$sql = "SELECT * FROM salesrec WHERE edate >= '$fromdate' AND edate <= '$todate' AND typ = 'nnon' AND div = '".USER_DIV."'";
	$recRslt = db_exec($sql) or errDie("Unable to access databse.", SELF);

	$i = 0;
	$ntot = 0;
	$ntotvat = 0;
	$ntotexc = 0;
	while ($rec = pg_fetch_array ($recRslt)) {

		# Calculate profit
		$ntot += $rec['total'];
		$ntotvat += $rec['vat'];
		$excvat = sprint($rec['total'] - $rec['vat']);
		$ntotexc += $excvat;


		$report .= "
						<tr bgcolor='".bgcolorg()."'>
							<td>$rec[invnum]</td>
							<td>$rec[edate]</td>
							<td>".CUR." $rec[vat]</td>
							<td>".CUR." $excvat</td>
							<td>".CUR." $rec[total]</td>
						</tr>";
		$i++;
	}

	$ntot = sprint($ntot);
	$ntotvat = sprint($ntotvat);
	$ntotexc = sprint($ntotexc);

	$atot = sprint($tot - $ntot);
	$atotvat = sprint($totvat - $ntotvat);
	$atotexc = sprint($totexc - $ntotexc);

	$report .= "
					<tr bgcolor='".bgcolorg()."'>
						<td colspan='2'><b>Total Non-Stock Credit Notes</b></td>
						<td>".CUR." $ntotvat</td>
						<td>".CUR." $ntotexc</td>
						<td>".CUR." $ntot</td>
					</tr>
					".TBL_BR."
					<tr>
						<td colspan='10'>
							<table ".TMPL_tblDflts." width='100%'>
								<tr>
									<th></th>
									<th>VAT</th>
									<th>SubTotal</th>
									<th>Total</th>
								</tr>
								<tr bgcolor='".bgcolorg()."'>
									<td><b>Total Non-Stock Sales after Credit Notes</td>
									<td><b>".CUR." $atotvat</td>
									<td><b>".CUR." $atotexc</td>
									<td><b>".CUR." $atot</td>
								</tr>
							</table>
						</td>
					</tr>
					".TBL_BR."
					<form action='".SELF."' method='POST'>
						<input type='hidden' name='key' value='export'>
						<input type='hidden' name='from_day' value='$from_day'>
						<input type='hidden' name='from_month' value='$from_month'>
						<input type='hidden' name='from_year' value='$from_year'>
						<input type='hidden' name='to_day' value='$to_day'>
						<input type='hidden' name='to_month' value='$to_month'>
						<input type='hidden' name='to_year' value='$to_year'>
						<tr>
							<td colspan='2'><input type='submit' value='Export to Spreadsheet'></td>
						</tr>
					</form>
				</table>
			    <p>
				<table ".TMPL_tblDflts." width='15%'>
			        ".TBL_BR."
			        <tr>
			        	<th>Quick Links</th>
			        </tr>
					<tr bgcolor='".bgcolorg()."'>
						<td><a href='sales-reports.php'>Sales Reports</a></td>
					</tr>
					<tr bgcolor='".bgcolorg()."'>
						<td><a href='main.php'>Main Menu</a></td>
					</tr>
				</table>";
	return $report;

}




function export ($_POST)
{
	
	# get vars
	extract ($_POST);

	# validate input
	require_lib("validate");
	$v = new  validate ();
	$v->isOk ($from_day, "num", 1,2, "Invalid from Date day.");
	$v->isOk ($from_month, "num", 1,2, "Invalid from Date month.");
	$v->isOk ($from_year, "num", 1,4, "Invalid from Date Year.");
	$v->isOk ($to_day, "num", 1,2, "Invalid to Date day.");
	$v->isOk ($to_month, "num", 1,2, "Invalid to Date month.");
	$v->isOk ($to_year, "num", 1,4, "Invalid to Date Year.");

	# mix dates
	$fromdate = $from_year."-".$from_month."-".$from_day;
	$todate = $to_year."-".$to_month."-".$to_day;

	if(!checkdate($from_month, $from_day, $from_year)){
			$v->isOk ($fromdate, "num", 1, 1, "Invalid from date.");
	}
	if(!checkdate($to_month, $to_day, $to_year)){
			$v->isOk ($todate, "num", 1, 1, "Invalid to date.");
	}

	# display errors, if any
	if ($v->isError ()) {
		$confirm = "";
		$errors = $v->getErrors();
		foreach ($errors as $e) {
			$confirm .= "<li class='err'>-".$e["msg"]."</li>";
		}
        return $confirm;
	}



	# connect to database
	db_connect ();

	// Layout
	$report = "
					<h3>Stock Sales Report</h3>
					<table ".TMPL_tblDflts.">
						<tr>
							<td colspan='5'><h3>Non-Stock Sales</h3></td>
						</tr>
						<tr>
							<th>Invoice no.</th>
							<th>Date</th>
							<th>VAT</th>
							<th>SubTotal</th>
							<th>Total</th>
						</tr>";

	# Get all relevant records
	db_connect();
	$sql = "SELECT * FROM salesrec WHERE edate >= '$fromdate' AND edate <= '$todate' AND typ = 'non' AND div = '".USER_DIV."'";
	$recRslt = db_exec($sql) or errDie("Unable to access databse.", SELF);

	$i = 0;
	$tot = 0;
	$totvat = 0;
	$totexc = 0;
	while ($rec = pg_fetch_array ($recRslt)) {

		# Calculate profit
		$tot += $rec['total'];
		$totvat += $rec['vat'];
		$excvat = sprint($rec['total'] - $rec['vat']);
		$totexc += $excvat;

		$report .= "
						<tr>
							<td>$rec[invnum]</td>
							<td>$rec[edate]</td>
							<td>".CUR." $rec[vat]</td>
							<td>".CUR." $excvat</td>
							<td>".CUR." $rec[total]</td>
						</tr>";
		$i++;
	}

	$tot = sprint($tot);
	$totvat = sprint($totvat);
	$totexc = sprint($totexc);

	$report .= "
					<tr>
						<td colspan='2'><b>Total Non-Stock Sales</b></td>
						<td>".CUR." $totvat</td>
						<td>".CUR." $totexc</td>
						<td>".CUR." $tot</td>
					</tr>
				</table>
			    <p>
				<table ".TMPL_tblDflts.">
					<tr>
						<td colspan='5'><h3>Non-Stock Credit Notes</h3></td>
					</tr>
					<tr>
						<th>Credit Note no.</th>
						<th>Date</th>
						<th>VAT</th>
						<th>SubTotal</th>
						<th>Total</th>
					</tr>";

	# Get all relevant records
	db_connect();
	$sql = "SELECT * FROM salesrec WHERE edate >= '$fromdate' AND edate <= '$todate' AND typ = 'nnon' AND div = '".USER_DIV."'";
	$recRslt = db_exec($sql) or errDie("Unable to access databse.", SELF);

	$i = 0;
	$ntot = 0;
	$ntotvat = 0;
	$ntotexc = 0;
	while ($rec = pg_fetch_array ($recRslt)) {

		# Calculate profit
		$ntot += $rec['total'];
		$ntotvat += $rec['vat'];
		$excvat = sprint($rec['total'] - $rec['vat']);
		$ntotexc += $excvat;

		$report .= "
						<tr>
							<td>$rec[invnum]</td>
							<td>$rec[edate]</td>
							<td>".CUR." $rec[vat]</td>
							<td>".CUR." $excvat</td>
							<td>".CUR." $rec[total]</td>
						</tr>";
		$i++;
	}

	$ntot = sprint($ntot);
	$ntotvat = sprint($ntotvat);
	$ntotexc = sprint($ntotexc);

	$atot = sprint($tot - $ntot);
	$atotvat = sprint($totvat - $ntotvat);
	$atotexc = sprint($totexc - $ntotexc);

	$report .= "
					<tr>
						<td colspan='2'><b>Total Non-Stock Credit Notes</b></td>
						<td>".CUR." $ntotvat</td>
						<td>".CUR." $ntotexc</td>
						<td>".CUR." $ntot</td>
					</tr>
					<tr><td><br></td></tr>
					<tr>
						<td colspan='10'>
							<table ".TMPL_tblDflts." width='100%'>
								<tr>
									<th></th>
									<th>VAT</th>
									<th>SubTotal</th>
									<th>Total</th>
								</tr>
								<tr>
									<td><b>Total Non-Stock Sales after Credit Notes</td>
									<td><b>".CUR." $atotvat</td>
									<td><b>".CUR." $atotexc</td>
									<td><b>".CUR." $atot</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>";

	include("xls/temp.xls.php");
	Stream("Report", $report);
	return $report;

}


?>