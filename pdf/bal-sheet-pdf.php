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

# Get settings
require("../settings.php");
require("../core-settings.php");

# decide what to do
if (isset($_POST["key"])) {
	switch ($_POST["key"]) {
		case "printsave":
			$OUTPUT = save_bal();
			break;

		default:
			$OUTPUT = bal_sheet();
	}
} else {
	# Display default output
	$OUTPUT = bal_sheet();
}

# Get templete
require("../template.php");

# Balance Sheet
function bal_sheet()
{
	$HEADER = "Balance sheet for period : ".PRD_NAME."\nDate: ".date("d M Y");
	$HEAD = "Owners Equity";
	$HEADINGS = array('accname' => "Account", 'balance' => "Balance", 'total' => "Total");
	$DATA = array();
	$HEAD2 = "Assets";
	$HEADINGS2 = array('accname' => "Account", 'balance' => "Balance", 'total' => "Total");
	$DATA2 = array();

	# Get Owners Equity Sub Headings
	$oesubRslt = get("core", "*", "bal_sheet", "type", "OESUB");
	$sheet = "
	<table>
	<tr><th colspan=3><h3> Balance sheet for period : ".PRD_NAME."</h3></th></tr>
	<tr><th colspan=3><h3>Date: ".date("d M Y")."</h3></th></tr>
	<tr><td colspan=3></td></tr>
	<tr><th colspan=3><h3>Owners Equity</h3></th></tr>";

	# Get accounts
	$oebal = 0; // OE Balance
	$i = 0;
	while($oesub = pg_fetch_array($oesubRslt)){
		$DATA[] = array('accname' => "<b><u>$oesub[value]</u></b>", 'balance' => "   ", 'total' => "   ");
		$sheet .= "<tr><td colspan=3><b><u>$oesub[value]</u></b></td></tr>";

		$sql = "SELECT * FROM bal_sheet WHERE type ='OEACC' AND ref = $oesub[ref] AND div = '".USER_DIV."'";
		$accRslt = db_exec($sql) or errDie("Unable to retrieve balance sheet settings from the Database.",SELF);
		# get account BAlances
		while($acc = pg_fetch_array($accRslt)){
			$query = "SELECT * FROM trial_bal WHERE period='".PRD_DB."' AND accid = '$acc[value]' AND div = '".USER_DIV."'";
			$balRslt = db_exec($query) or errDie("Unable to retrieve Account Balances from the Database.",SELF);
			$accbal = pg_fetch_array($balRslt);
			$balance = ($accbal['credit'] - $accbal['debit']);
			$oebal += $balance;
			$DATA[] = array('accname' => $accbal['accname'], 'balance' => CUR." $balance", 'total' => "  ");
			$sheet .= "<tr><td>$accbal[accname]</td><td>".CUR." $balance</td><td><br></td></tr>";
		}
	}
	# Print Net Income and OE Balance on last column
	$netincome = getNetIncome();
	$oebal += $netincome;
	$DATA[] = array('accname' => "<b>Net Income</b>", 'balance' => CUR." $netincome", 'total' => "  ");
	$DATA[] = array('accname' => "  ", 'balance' => "  ", 'total' => "  ");
	$DATA[] = array('accname' => "<b>Total</b>", 'balance' => "  ", 'total' => CUR." $oebal");

	$sheet .="<tr><td>&nbsp<b>Net Income</b></td><td>".CUR." $netincome</td><td><br></td></tr>";
	$sheet .="<tr><td colspan=2><b>Total</b></td><td><b>".CUR." $oebal</b></td></tr>
	<tr><td colspan=3></td></tr>";

	# Get Assets Sub Headings
	$abal = 0; // Assets Balance
	$asssubRslt = get("core", "*", "bal_sheet", "type", "ASSSUB");
	$sheet .= "<tr><th colspan=3><h3>Assets</h3></th></tr>";

	# Get accounts
	while($asssub = pg_fetch_array($asssubRslt)){
		$DATA2[] = array('accname' => "<b><u>$asssub[value]</u></b>", 'balance' => "   ", 'total' => "   ");
		$sheet .= "<tr><td colspan=3><b><u>$asssub[value]</u></b></td></tr>";

		$sql = "SELECT * FROM bal_sheet WHERE type ='ASSACC' AND ref = $asssub[ref] AND div = '".USER_DIV."'";
		$accRslt = db_exec($sql) or errDie("Unable to retrieve balance sheet settings from the Database.",SELF);
		# Get account BAlances
		while($acc = pg_fetch_array($accRslt)){
			$query = "SELECT * FROM trial_bal WHERE period='".PRD_DB."' AND accid = '$acc[value]' AND div = '".USER_DIV."'";
			$balRslt = db_exec($query) or errDie("Unable to retrieve Account Balances from the Database.",SELF);
			$accbal = pg_fetch_array($balRslt);
			$balance = ($accbal['debit'] - $accbal['credit']); // calc Balance
			$abal += $balance;
			$DATA2[] = array('accname' => $accbal['accname'], 'balance' => CUR." $balance", 'total' => "  ");
			$sheet .= "<tr><td>$accbal[accname]</td><td>".CUR." $balance</td><td><br></td></tr>";
		}
	}

	# Print assets balance on last column
	$DATA2[] = array('accname' => "  ", 'balance' => "  ", 'total' => "  ");
	$DATA2[] = array('accname' => "<b>Total</b>", 'balance' => "  ", 'total' => CUR." $abal");
	$sheet .="<tr><td colspan=2><b>Total</b></td><td><b>".CUR." $abal</b></td></tr>
	</table>";

	# Stream out to browser
	require("temp.pdf.php");
}

// Get total income
function getNetIncome()
{
	# Get the income statement settings
	core_connect();
	$sql = "SELECT accid FROM accounts WHERE acctype='I' AND div = '".USER_DIV."'";
	$incRslt = db_exec($sql) or errDie("Unable to retrieve income statement settings from the Database",SELF);
	if(pg_numrows($incRslt) < 1){
		return "<center>There Are no Income in Cubit.";
	}

	# Get income accounts Balances
	$tlinc = 0; // total income credit

	while($inc = pg_fetch_array($incRslt)){
		# get the balances (debit nad credit) from trial Balance
		$sql = "SELECT * FROM trial_bal WHERE period='".PRD_DB."' AND accid = '$inc[accid]' AND div = '".USER_DIV."'";
		$balRslt = db_exec($sql) or errDie("Unable to retrieve Account Balance information from the Database.",SELF);
		$bal = pg_fetch_array($balRslt);

		$total = ($bal['credit'] - $bal['debit']);
		$tlinc += $total;
	}

	# Get the income statement settings
	$sql = "SELECT accid FROM accounts WHERE acctype='E' AND div = '".USER_DIV."'";
	$expRslt = db_exec($sql) or errDie("Unable to retrieve income statement settings from the Database",SELF);
	if(pg_numrows($expRslt) < 1){
		return "<center>There Are no Expenditure accounts in Cubit.";
	}

	# Get account Balances for Expenditure
	$tlexp = 0; // total expenditures

	while($exp = pg_fetch_array($expRslt)){
		# Get vars from inc (accnum, type)
		foreach($exp as $key => $value){
			$$key = $value;
		}

		# Get the balances (debit nad credit) from trial Balance
		$sql = "SELECT * FROM trial_bal WHERE period='".PRD_DB."' AND accid = '$exp[accid]' AND div = '".USER_DIV."'";
		$balRslt = db_exec($sql) or errDie("Unable to retrieve Account Balance information from the Database.",SELF);
		$bal = pg_fetch_array($balRslt);

		# Alternate bgcolor
		$total = ($bal['debit'] - $bal['credit']);
		$tlexp += $total;        // And increment the balance for expenditure
	}
	return sprintf("%01.2f", ($tlinc - $tlexp));
}
?>
