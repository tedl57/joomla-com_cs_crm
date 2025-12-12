<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Cs_crm
 * @author     Ted Lowe <lists@creativespirits.org>
 * @copyright  2018 Ted Lowe
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Include dependancies
jimport('joomla.application.component.controller');

JLoader::registerPrefix('Cs_crm', JPATH_COMPONENT);
JLoader::register('Cs_crmController', JPATH_COMPONENT . '/controller.php');

$jinput = JFactory::getApplication()->input;

// check if this is the first time the app has run in this browser session
// if the scope is not currently set, set it now which will trigger "app boot"

$scope = Cs_crmHelpersCs_crm::sessionGetVar("scope",null);
if ( $scope === null )
{
	Cs_crmHelpersCs_crm::appBoot();
	setscope( Cs_crmHelpersCs_crm::DEFAULT_SCOPE );
}

// check if an app-defined action is required
$action = $jinput->get->get('action',null,'cmd');
if ( $action != null )
{
	$actid = $jinput->get->get('actid',null,'string');
	handleAction( $action, $actid );	// in controller, does not return, it redirects back here w/o the action parm
}

// Execute the task.
$controller = JControllerLegacy::getInstance('Cs_crm');
$controller->execute($jinput->get('task'));

$controller->redirect();

/////////////////// FUNCTIONS /////////////////////////////

function handleAction($action,$actid)
{
	// no controller actions are allowed by guests (unlogged in users), except the cron task(s)
	if (!Cs_crmHelpersCs_crm::isUserAuth($action))	// authtodo: handleAction() in controller
		return;
	
	switch( $action )
	{
	case "setscope":
	case "showroster":
	case "showmemberenterpaymentform":
	case "gotomember":
		$action( $actid );
		break;
	case "browse":
	case "showaddress":
	case "showdata":
	case "update":
	case "addnote":
	case "addnew":
	case "savenew":
	case "dorenewalreminders":
	case "showenterpaymentform":
	case "enterpayment":
	case "runplugin":
		$action();
		break;
	default:
		break;
	}
	
	// remove action & actid and go to view
	$uri = JUri::getInstance();
	$uri->delVar("action");
	$uri->delVar("actid");
	$app    = JFactory::getApplication();
	$app->redirect($uri->__toString());
}
function gotomember($actid)
{
	// used to resolve links in renewal reminders output
	setscope("All",false);
	$curid = findRecordIndexById($actid);
	gotoRecord( $curid );
}
function change_status( $data, $newstatus, $updatestatus, $verbose = true)
{
	$oldstatus = $data["status"];

	if ( $updatestatus )
	{
		$obj = new stdClass();
		$obj->id = $data["id"];
		$obj->last_updated = $obj->status_updated = date('Y-m-d H:i:s');
		$obj->status = $newstatus;
		$result = JFactory::getDbo()->updateObject(Cs_crmHelpersCs_crm::getTableName("members"), $obj, 'id');

		//2014-08-20 04:58:05-auto	update: status=Not Renewed(Active);
		Cs_crmHelpersCs_crm::addNote( $obj->id, "update: status=$newstatus($oldstatus)" );
	}
	
	if ( $oldstatus == "Active" && $newstatus != "Active" )	// specifically Not Renewed (automatic renewal reminders) or manual Not Renewing
		onDeactivate($data,$verbose);
}
function do_renewalreminder( $rec, $soon, &$db, $today,$max_renewal_reminders,$renewal_reminder_spacing )
{
	// output member's information
	$id = $rec["id"];
	$hrefid = "<a href='/index.php?option=com_cs_crm&action=gotomember&actid=$id'>$id</a>";
	printf( "%s [%s] %s %s (%s)",
			$rec["paidthru"],$rec["memtype"],$rec["fname"],	$rec["lname"],$hrefid );

	// tmp xxx todo: add forumsid too
	
	$flds = array( "id", "reminder_date", "reminders_sent", "last_reminder", "archived");
	$selstr = implode( ",", $flds );

	$memid = $rec["id"];
	$paidthru = $rec["paidthru"];
	$sql = "SELECT $selstr FROM #__cs_reminders WHERE reminder_type='renewal' AND memid='$memid' AND reminder_date='$paidthru'";
	//echo "sql=$sql<br>";
	$db->setQuery($sql);
	$remrec = $db->loadAssocList();
	
	$c = count( $remrec );

	if ( $c > 1 )
		die("ERROR: $c reminder records for memid=$memid!");

	$ret = false;

	if ( $c == 0 )
	{
		$msg = "Sent first reminder.";
		$color = "green";

		// save the initial renewal reminder entry
		
		$obj = new stdClass();
		$obj->memid = $memid;
		$obj->reminder_type = 'renewal';
		$obj->reminder_date = $paidthru;
		$obj->reminders_sent = 1;
		$obj->last_reminder = $today;
		$obj->archived = 0;

		$result = $db->insertObject( "#__cs_reminders", $obj, 'id' );

		send_renewal_reminder( $rec, 1, $max_renewal_reminders, $soon, $db );
		$ret = true;
	}
	else
	{
		// check if there are more reminders to send
		if ( $remrec[0]["archived"] == 1 )
		{
			// todo: this feature isn't really used; can't remember why i had added it long ago; no way to set it except in phpMyAdmin
			$msg = "Renewal reminders have been suspended.";
			$color = "peru";
		}
		else if ( $remrec[0]["reminders_sent"] >= $max_renewal_reminders )
		{
			$nextdate = getDateNewDays( $renewal_reminder_spacing, $remrec[0]["last_reminder"] );

			if ( $today >= $nextdate )
			{
				$rec["status"] = "Active";	// add status to cause status change trigger				 
				change_status( $rec, "Not Renewed", true, false);			
				$msg = "Set status=Not Renewed!";
			}
			else
				$msg = "Maximum ($max_renewal_reminders) reminders sent; will set status=Not Renewed on $nextdate";
			$color = "red";
		}
		else
		{
			// check if its time to send another reminder
			$nextdate = getDateNewDays( $renewal_reminder_spacing, $remrec[0]["last_reminder"] );
			$n = $remrec[0]["reminders_sent"] + 1;

			if ( $nextdate > $today )
			{
				$msg = "Too soon to send reminder $n ($nextdate).";
				$color = "brown";
			}
			else
			{
				$msg = "Sent reminder $n.";
				$color =  $n == 2  ? "yellow" : ( $n == 3  ? "orange" : "red");

				$obj = new stdClass();
				$obj->id = $remrec[0]["id"];
				$obj->reminders_sent = "$n";
				$obj->last_reminder = $today;
				$result = $db->updateObject('#__cs_reminders', $obj, 'id');

				send_renewal_reminder( $rec, $n, $max_renewal_reminders, $soon, $db );
				$ret = true;
			}
		}
	}
	echo "<span style='color: $color;'>&nbsp;&nbsp;$msg</span><br>";
	
	// return true if a renewal reminder email was sent out
	return $ret;
}
function getDateNewDays( $daysdiff = 0, $curdate = "" ) // rrtodo: put in helpers or libcs
{
	// return mysql format yyyy-mm-dd
	if ( empty( $curdate ) )
		$newdate = getdate();
	else
		$newdate = getdate(Cs_crmHelpersCs_crm::getUnixTimestampFromMysql($curdate));

	return date( "Y-m-d", $newdate[0] + ( $daysdiff * 24 * 60 * 60 ) );
}
function send_renewal_reminder( $rec, $n, $max_renewal_reminders, $soon, &$db )
{
	$tmpl_type = $soon ? "duesoon" : "pastdue";
	$rec["reminder_subject_prefix"] = ($n == $max_renewal_reminders) ? "Last Reminder! " : "Reminder: ";

	// todo: check fname, lname and email

	$emailobj = new LibcsEmailSender("renewal_reminder_$tmpl_type","com_cs_crm");

	$to = $rec["email"];
	/*rrtodo: testing hook commented out on live site 12/28/19
	if ( $rec["lname"] != "Rowe" )	// rrtodo: while testing (aka testing easter egg)
		$to = "testemail@example.com";
	*/
	$emailobj->addTo( $to, $rec["fname"] . " " . $rec["lname"]);
	$ret = $emailobj->send($rec);
	if ( $ret )	
	{
		echo " emailed to $to!";

		// add note about the renewal reminder being sent
		$note = "contact: renewal reminder $n emailed to $to for paidthru " . $rec["paidthru"];
		Cs_crmHelpersCs_crm::addNote( $rec["id"], $note );
	}
	else
		echo " EMAIL to $to FAILED!";
}
function dorenewalreminders()
{
	// called by cron once per day to send out renewal reminder emails as necessary 
	// and possibly deactivate users that don't renew
	
	// check whether if this action has already been run today
	// special entries with type=renewalreminders are used for this purpose

	$today = date('Y-m-d');	// outputs: 2018-12-31
	$sql = "SELECT id FROM #__cs_reminders WHERE reminder_type='renewalreminders' AND reminder_date='$today'";
	$db = JFactory::getDBO();
	$db->setQuery($sql);
	$row = $db->loadAssoc();
	if (is_array($row))
	{
		echo "renewalreminders SKIPPED $today\n";
		die;	
	}
	// save special record to indicate renewal reminders have been done today (todo: not quite atomic)
	$obj = new stdClass();
	$obj->id = 0;	// will be replaced with new auto_increment id after insert
	$obj->reminder_date = $today;
	$obj->reminder_type = "renewalreminders";
	$result = $db->insertObject( "#__cs_reminders", $obj, 'id');
	
	// import library class for sending the reminder emails to members and the summary to the membership manager(s)
	
	jimport('libcs.email.sender');
	
	// capture output for putting into a file for permanent storage
	
	ob_start();
	
	// determine records that need reminders
	$start_renewal_reminder_before = 21;	// rrtodo: make these component params
	$max_renewal_reminders = 3;
	$renewal_reminder_spacing = 14;
	$futuredate = getDateNewDays( $start_renewal_reminder_before, $today );

	$flds = array( "id", "email", "fname", "lname", "paidthru", "memtype", "cmsid", "forumsid" );
	$selstr = implode(",", $flds);
	$sql = "SELECT $selstr FROM #__cs_members WHERE status='Active' AND paidthru!='' AND email!='' AND paidthru<'$today' ORDER BY paidthru";
	$db->setQuery($sql);
	$pastdue = $db->loadAssocList();
	$sql = "SELECT $selstr FROM #__cs_members WHERE status='Active' AND paidthru!='' AND email!='' AND paidthru<'$futuredate' AND paidthru>='$today'";
	$db->setQuery($sql);
	$soondue = $db->loadAssocList();
	
	$nsent = 0;
	$c = count($soondue);
	echo "<H4>$c due soon with email between $today and $futuredate</h4>";
	foreach( $soondue as $rec )
		if ( do_renewalreminder( $rec, true, $db, $today,$max_renewal_reminders,$renewal_reminder_spacing ) )
			$nsent++;
			
	$c = count($pastdue);
	echo "<H4>$c past due with email as of $today</h4>";
	foreach( $pastdue as $rec )
		if ( do_renewalreminder( $rec, false, $db, $today,$max_renewal_reminders,$renewal_reminder_spacing ) )
			$nsent++;
		
	// collect renewal reminder summary output to put in a file and email to the memembership manager
	
	$outp = ob_get_clean();
	
	// process the links to allow clickability in email
	// rrtodo: document component dependency com_cs_crm now dependent on com_cs_payments
	// ntodo: add a component parameter to THIS component to break dependency w/ com_cs_payments
	// ntodo: AND to solve problem of not working in a subfolder eg, http://localhost/joomla39
	$org_website = JComponentHelper::getParams("com_cs_payments")->get("org_web_address",""); 
	$outp = str_replace( "href='/index.php?", "href='" . $org_website . "/index.php?",$outp);

	$emailobj = new LibcsEmailSender("renewal_reminder_summary","com_cs_crm");

	$emailobj->NoBccSender();
	
	// rrtodo: mkdir() recursively or document that this folder must exist
	// rrtodo: component parameter for path to summary files
	$dir = "docs/membership/renewal-reminders";
	$file = "$dir/$today.html";
	$ret = file_put_contents($file,$outp);
	if ( $ret === FALSE )
		echo "renewalreminders ERROR writing $file\n";
	else
		echo "renewalreminders RAN $file\n";

	// rrtodo: this is hardcoded and won't work with max_reminders other than 3
	$count_patterns = array(
			"sent1" => "Sent first reminder\.",
			"will_send2" => "Too soon to send reminder 2",
			"sent2" => "Sent reminder 2\.",
			"will_send3" => "Too soon to send reminder 3",
			"sent3" => "Sent reminder 3\.",
			"will_not_renewed" => "Maximum \(3\) reminders sent;",
			"not_renewed" => "Set status=Not Renewed!" );
	
	$data = array("run_date" => $today );

	foreach( $count_patterns as $k => $v )
		$data[$k] = preg_match_all("/".$v."/",$outp);
		
	$data["body"] = $outp;
	
	$ret = $emailobj->send($data, true); // html email
	
	// update renewreminders record to indicate how many renewal reminder emails were actually sent out

	$updobj = new stdClass();
	$updobj->id = $obj->id;	// id of the record inserted above
	$updobj->reminders_sent = $nsent;
	$result = JFactory::getDbo()->updateObject("#__cs_reminders", $updobj, 'id');

	die;
}
function showmemberenterpaymentform($actid)
{
	// set the scope properly based on the members status, find the member in that scope by memid, show the enter payment form

	Cs_crmHelpersCs_crm::sessionSetVar("showenterpaymentform",1);

	setscope("All",false);
	$curid = findRecordIndexById($actid);
	gotoRecord( $curid );

	//JFactory::getApplication()->enqueueMessage("showmemberenterpaymentform: memid=$actid",'success');
}
function showroster($actid)
{
	// https://domain/index.php?option=com_cs_crm&action=showroster
	// generate and store roster to be showed in the view
	
	$print = $actid == "print";

	ob_start();
	
	$sql = "SELECT * FROM " . Cs_crmHelpersCs_crm::getTableName("members") . " WHERE status='Active' ORDER BY lname ASC";
	$db = JFactory::getDBO();
	$db->setQuery($sql);
	$rows = $db->loadAssocList();
	$n = count($rows);

	$now = Cs_crmHelpersCs_crm::getDateNewYear();

	if ( $print )
		$href = "<a href='' onclick='javascript: window.print(); return false'>Print</a>";
	else
		$href = Cs_crmHelpersCs_crm::getHrefPopup("index.php?option=com_cs_crm&action=showroster&actid=print","Printer Friendly");
	
	echo "<p style='font-weight: bold;'>Membership Roster as of $now - $n Active Members - $href</p>";
	// todo        Printer Friendly
	
	// preprocess data to check for missing names before sort
	for( $i = 0 ;$i < $n ; $i++ )
	{
		if ( empty($rows[$i]["fname"]) && empty($rows[$i]["lname"]) )
			$rows[$i]["lname"] = $rows[$i]["bname"];
	}
	usort($rows,"usortlname");
	echo "<table cellspacing=2 cellpadding=2 border='1'>";
	echo "<tr>";
	$nactive = 0;
	$i = 0;
	foreach( $rows as $row )
	{
		$nactive++;
		if ( $i++ % 3 == 0 )
			echo "</tr><tr>";
		$label = mk_label($row);
		echo "<td style='padding: 1px 0 1px 3px;'>$label</td>";
	}
	echo "</tr></table>";

	$roster = ob_get_contents();
	ob_end_clean();
	
	if ( $print )
	{
		jexit($roster);
	}
	Cs_crmHelpersCs_crm::sessionSetVar("showroster",$roster);
}
function usortlname($a,$b)
{
	return strcasecmp($a["lname"],$b["lname"]);
}
function mk_label($row)
{
	//print_r($row);echo "<p>";
	extract($row);
	if ( empty( $roster_phone ) || $roster_phone == NULL )
	{
		// display cell phone if it is set, else home, else work, else nothing
		if ( (! empty( $cphone ) )&& ($cphone != NULL ) )
			$roster_phone = "c";
		else if ( (! empty( $hphone ) )&& ($hphone != NULL ) )
			$roster_phone = "h";
		else if ( (! empty( $wphone ) )&& ($wphone != NULL ) )
			$roster_phone = "w";
	}

	$phone_var = $roster_phone . "phone";
	if ( isset( ${$phone_var} ) )
		$phone = ${$phone_var};
	else
		$phone = "";
	if ( ! empty( $phone ) )
		$phone .= " ($roster_phone)";

	if ( empty($fname) && empty($lname) )
		// must be a business
		$n = "<span style='font-weight: bold;'>" . ucwords("$bname") . "</span><br />";
	else
		$n = ucwords("$fname $lname") . "<br />";

	$a = ucwords($address) . "<br />";
	$csz = ucwords($city) . ", " . strtoupper($state) . " " . $zip . "<br>";
	if ( (empty($address)||empty($city)||empty($state)||empty($zip))) // handle missing address info
	{
		$a = $csz = "<br />";
	}
	$p = "$phone<br />";
	$e = "$email<br />";

	return $n . $a . $csz . $p . $e;
}
function runplugin()
{
/*
JFactory::getApplication()->enqueueMessage("_POST:<br />". str_replace(",","<br />",json_encode($_POST)),'success');
_POST:
{"plugin":"Example1"}
*/

	/* to run a plugin:
	 * 1. lookup the plugin name from the component's parameters
	 * 2. include the plugin file
	 * 3. instantiate a plugin class
	 * 4. execute the plugin passing in the current data
	 */

	$plugin_file = Cs_crmHelpersCs_crm::getPluginFile($_POST["plugin"]);
	 
	@include $plugin_file;
	if (class_exists("userPlugin") !== TRUE)
		return;

	$data = Cs_crmHelpersCs_crm::sessionGetVar("data");
	$pluginobj = new userPlugin;
	$ret = $pluginobj->execute($data);

	if ( ! $ret )
	{
		$uri = Cs_crmHelpersCs_crm::getURI("");	// todo: waste of time sending blank action
		jexit("<br />Plugin completed. <a href='$uri'>Return to application</a>.");
	}
}
function enterpayment()
{
	/*
_POST:
{"amount_paid":"20"
"paid_through_new":"2019-11-29"
"payment_method":"Online"
"where_received":"PayPal"
"payment_term":"1"
"payment_note":"a note"
"do_operation":"Activate"
"memid":"123"
"payment_id":"123" // can also be blank if manually initiated payment
"automate":"1"} 
	 */
//JFactory::getApplication()->enqueueMessage("_POST:<br />". str_replace(",","<br />",json_encode($_POST)),'success');

	/* 
	 * 0. validate POSTED data
	 * 1. enter amount paid in finances table
	 * 2. update member's paid through date 
	 * 3. add note to record
	 * 4. perform relevant do_operation
	 * 		a) Activate - create new Joomla! user record, new forums user, email new user
	 * 		b) Reactivate - email "returning" member
	 *		c) Renew - email "renewing" member
	 */

	// make sure all expected / needed fields are set and not empty
	$flds = array( "memid", "amount_paid", "paid_through_new", "payment_method", "where_received", "payment_term", "do_operation" );
	
	foreach( $flds as $fld )
		if ( (! isset($_POST[$fld])) || empty( $_POST[$fld]) )
		{
			JFactory::getApplication()->enqueueMessage("Payment failed: no $fld",'error');
			return;
		}

	$automate = isset( $_POST["automate"]);
	$note = isset( $_POST["payment_note"]) ? $_POST["payment_note"] : "";
	$data = Cs_crmHelpersCs_crm::sessionGetVar("data");

	// compare id in POST vs in SESSION to help prevent spoofing
	
	if ( $_POST["memid"] != $data["id"] )
	{
		JFactory::getApplication()->enqueueMessage("Payment failed: bad id",'error');
		return;
	}	
	
	$operations = array( "Renew", "Activate", "Reactivate");
	$op = $_POST["do_operation"];
	
	if ( ! in_array( $op, $operations ) )
	{
		JFactory::getApplication()->enqueueMessage("Payment failed: bad operation",'error');
		return;	
	}
	
	// add payment (finances) record
	$payment_typ = ( $op == "Activate") ? "join" : "renew";
	addPayment( $data["id"], $data["memtype"], $payment_typ, 
			$_POST["amount_paid"], $_POST["payment_method"], 
			$_POST["where_received"], $_POST["payment_term"], $note );
	
	// update member's record with new paid through date and possible new status
	
	$data_updated = array();
	$data_updated[ "paidthru"] = $data["paidthru"] = $_POST["paid_through_new"];

	// operation specific actions
	
	if ($op != "Renew")
	{
		$data_updated["status"] = "Active";
		
		if ( $op == "Activate" )
			$data_updated["member_since"] = date('Y-m-d');
	}
	 
	updateFromArray($data_updated);	// enterpayment()
	
	// update cs_payments record if this is is an IPN payment
	
	if (!empty( $_POST["payment_id"]))
	{
		// mark the IPN payment "processed"		
	
		$obj = new stdClass();
		$obj->id = $_POST["payment_id"];
		$obj->processed_date = date('Y-m-d H:i:s' );
		$obj->processed_by = JFactory::getUser()->get('username');
		$res = JFactory::getDbo()->updateObject("#__cs_payments", $obj, 'id');
		
		JFactory::getApplication()->enqueueMessage("Processed payment #" . $obj->id . "<br />", 'success');
	}
	
	if ( $automate )	// onActivate(), OnRenew(), and OnReactivate()
	{
		// notify member of payment received/status change
		$notify_function = "on$op";
		$notify_function($data);	// onerenew, onactivate, onreactivate
	}
}
function onDeactivate($data,$verbose)
{
	// check to see if there is an application specific extension to execute
	$whatapp = Cs_crmHelpersCs_crm::whatApp() == "m" ? "members" : "contacts";

	// optionally find/include extension file that may define user specified functions
	
	@include_once "components/com_cs_crm/helpers/plugins/$whatapp/__siteplugins.php";

	// see if this site has extended this action
	if ( function_exists("userOnDeactivate"))
		userOnDeactivate($data,$verbose);
}
function onActivate($data,$verbose=true)
{
	// check to see if there is an application specific extension to execute
	$whatapp = Cs_crmHelpersCs_crm::whatApp() == "m" ? "members" : "contacts";

	// optionally find/include extension file that may define user specified functions
	@include_once "components/com_cs_crm/helpers/plugins/$whatapp/__siteplugins.php";

	// see if this site has extended this action
	if ( function_exists("userOnActivate"))
		userOnActivate($data,$verbose);
}
function onReactivate($data)
{	
	// a member has renewed after being inactive
	
	// email member a thank you message
	 
	onRenew($data,false);

	// todo: unblock cms user

	// todo: userOnReactivate() - unban user from forums
	
	JFactory::getApplication()->enqueueMessage("onReactivate: emailed thank you message",'success');
	
	// check to see if there is an application specific extension to execute
	$whatapp = Cs_crmHelpersCs_crm::whatApp() == "m" ? "members" : "contacts";
	
	// optionally find/include extension file that may define user specified functions
	@include_once "components/com_cs_crm/helpers/plugins/$whatapp/__siteplugins.php";
	
	// see if this site has extended this action
	if ( function_exists("userOnReactivate"))
		userOnReactivate($data,$verbose);
}
function onRenew($data,$confirm=true)
{
	// email member thank you message
	
	// import library class for sending email

	jimport('libcs.email.sender');

	// todo check fname, lname and email
	
	$emailobj = new LibcsEmailSender("renewal","com_cs_crm");
	$emailobj->addTo( $data["email"], $data["fname"] . " " . $data["lname"] );
	$ret = $emailobj->send($data);
	
//$emailobj->dump();
//jexit();	
	
	if ( $confirm )
	{
		$result = $ret ? "success" : "error";
	
		JFactory::getApplication()->enqueueMessage("onRenew: ".$emailobj->__tostring(), $result );
	}
}
function addPayment( $id, $memtype, $typ, $amount, $method, $rcvd, $term, $note )
{
	// todo: add more info from __cs_payments record - see below ?
	$obj = new stdClass();
	$obj->id = 0;	// returned FID will be here
	$obj->memid = $id;
	$obj->income_expense = "income";
	$obj->for_what = $typ;
	$obj->amount = $amount;
	$obj->payment_method = $method;
	$obj->for_what_type = $memtype;
	$obj->where_received = $rcvd;
	$obj->payment_term = $term;
	$obj->note = $note;
	$obj->date_entered = date('Y-m-d H:i:s');
	$obj->by_username = JFactory::getUser()->get('username');

	$result = JFactory::getDbo()->insertObject( "#__cs_finances", $obj, 'id' );
	$fid = $obj->id;
	$ipn = "";
/*
	 if ( getUnprocessedPayment( $typ, $id, true, $fid ) > 0 )
	 	$ipn = "(IPN)"
*/
	$note = "$typ: FID=$fid \$" . $amount . " " . $method . "$ipn " . $rcvd . " " . $note;

	Cs_crmHelpersCs_crm::addNote( $id, $note );
}
function showenterpaymentform()
{
	Cs_crmHelpersCs_crm::sessionSetVar("showenterpaymentform",1);
}
function addnote()
{
	if ( isset($_POST["note"]) && (!empty($_POST["note"])) && isset($_POST["memid"]) )
		Cs_crmHelpersCs_crm::addNote( $_POST["memid"], $_POST["note"] );
}
function changesToString( $changes )
{
	$ret = "";

	foreach( $changes as $k => $v )
		$ret .= ( $k . "=" . $v["new"] . "(" . $v["old"] . "); " );

	return $ret;
}
function savenew()
{
	if ( isset($_POST["cancel"]) && $_POST["cancel"] == "cancel")
		return cancelnew();

//todo: JFactory::getApplication()->enqueueMessage("_POST:<br />". str_replace(",","<br />",json_encode($_POST)),'success');
	
	// insert new note record into the db linked to the member/contact record
	
	unset( $_POST["update"] );	// the submit button

	$obj = new stdClass();
	$obj->id = 0;			// will be replaced with new auto_increment id after insert

	foreach ( $_POST as $k => $v )
	{
		// only save non-empty and selected fields
		if (empty($v) || $v == Cs_crmHelpersCs_crm::DEFAULT_SELECT )
			continue;
		$obj->$k = $v;
	}

	$obj->date_entered = date('Y-m-d H:i:s', time() );
	
	// make sure status is set

	if ( ! isset( $obj->status ) )
		$obj->status = Cs_crmHelpersCs_crm::isMemDB() ? "Applied" : "Active";	// todo: assuming initial state of new record 
	
	$result = JFactory::getDbo()->insertObject( Cs_crmHelpersCs_crm::getTableName("members"), $obj, 'id');

	$scope = Cs_crmHelpersCs_crm::sessionGetVar("scope",null);	// todo: scope should not be null

	// does scope need to be changed from previous scope to see this new record?
	
	$nscope = getBestScope( $obj->status, $scope );
	
	// reread list of ids with (new) scope to include new record

	setscope($nscope,false);
	
	// find the new record in reread scope and read it into the session for the view to display

	$curid = findRecordIndexById($obj->id);
	if ( $curid == 0 )
		return setscope( "All");	// todo: something went wrong
	else 
		gotoRecord( $curid );

	JFactory::getApplication()->enqueueMessage("Added new record # " . $obj->id,'success');
	if ( $nscope != $scope )
		JFactory::getApplication()->enqueueMessage("<br />Changed scope to show new record.", 'success');
}
function getBestScope( $status, $scope )
{
	if (inScope( $status, $scope))
		return $scope;	// best scope is the current one if it is in scope
	
	// else, best scope is the smallest scope including the new status

	switch($status)
	{
	case 'Applied':
		return "Applied";
	case 'Active':
		return "Active";
	default:
		return "Not Active";
	}
	return "All"; // todo: should not get here
}
function findRecordIndexById($memid)
{
	// search through the current list of IDs until the record id is found, return the index + 1 (humanly displayed/stored version)
	
	$idlist = Cs_crmHelpersCs_crm::sessionGetVar("idlist");
	$nids = count($idlist);
	for ( $ndx = 0 ; $ndx < $nids ; $ndx++ )
		if ($idlist[$ndx] == $memid)
			return $ndx+1;
	return 0;
}
function inScope( $nstatus, $scope )
{
	switch( $scope )
	{
	case 'All':
		return true;
	case 'Active/Applied':
		return $nstatus == "Active" || $nstatus == "Applied";
	case 'Active':
		return $nstatus == "Active";
	case 'Applied':
		return $nstatus == "Applied";
	case 'Not Active':
		return $nstatus != "Active";
	default:
		return false;
	}
	return false;
}

function update()
{
	updateFromArray($_POST);	// update()
}
function updateFromArray($arr)
{
	$data = Cs_crmHelpersCs_crm::sessionGetVar("data");

	/*
	 $alldata = str_replace( ',',',<br .>', json_encode($arr) );
	 JFactory::getApplication()->enqueueMessage("Update arr: <br /><br />$alldata<br />",'success');
	
	 $alldata = str_replace( ',',',<br .>', json_encode($data) );
	 JFactory::getApplication()->enqueueMessage("Update data: <br /><br />$alldata<br />",'success');
	 */
	
	// determine what data is being updated (if any)

	$chdata = array();
	$obj = new stdClass();
	
	foreach ( $arr as $k => $v )
		if ( array_key_exists( $k, $data ) && $v != $data[$k] && $v != Cs_crmHelpersCs_crm::DEFAULT_SELECT )
		{
			$chdata[$k]["new"] = $v;
			$chdata[$k]["old"] = $data[$k];
			$obj->$k = $v;
		}
	
	if ( count($chdata) == 0)
		return;	// todo: no changes made, should put out notice?
	
	// update timestamp of last status change as necessary
	$status_changed = array_key_exists("status", $chdata);
	if ($status_changed)
	{
		$obj->status_updated = date('Y-m-d H:i:s');
		change_status( $data, $chdata["status"]["new"], false, true);
	}

	// add a note to track the changes to the record
	$c2s = changesToString( $chdata );
//	JFactory::getApplication()->enqueueMessage("Update-Changes:<br />c2s: $c2s<br />chdata: ".json_encode($chdata) ."<br />",'success');
	Cs_crmHelpersCs_crm::addNote( $data["id"], "update: $c2s" );

	// update the DB with the changes
	$obj->id = $data["id"];
	$obj->last_updated = date('Y-m-d H:i:s');
	$result = JFactory::getDbo()->updateObject(Cs_crmHelpersCs_crm::getTableName("members"), $obj, 'id');
				
	// if the record's status changed, handle a posssible scope change
	if ( $status_changed )
	{
		$scope = Cs_crmHelpersCs_crm::sessionGetVar("scope",null);

		// does scope need to be changed from previous scope to see this new record?
			
		$nscope = getBestScope( $chdata["status"]["new"], $scope );
		
		if ( $nscope != $scope )
		{	
			// reread list of ids with (new) scope to include new record
			
			setscope($nscope,false);
			
			// find the new record in reread scope and read it into the session for the view to display
			
			$curid = findRecordIndexById($obj->id);
			if ( $curid == 0 )
				return setscope( "All");	// todo: something went wrong
			else
				gotoRecord( $curid );
			
			JFactory::getApplication()->enqueueMessage("Changed scope due to status change.",'info');		

			return;
		}
	}

	// reread the updated DB record into the session for display
	Cs_crmHelpersCs_crm::readDataIntoSession($data["id"]);
}
function showaddress()
{
	$app = JFactory::getApplication();
	$data = Cs_crmHelpersCs_crm::sessionGetVar("data");
	$name = $data["fname"] . " " . $data["lname"];
	$address = $data["address"];
	$csz = $data["city"] . ", " . $data["state"] . " " . $data["zip"];
	
	$app->enqueueMessage("Mailing Label:<br /><br />$name<br />$address<br />$csz<br />",'success');
}
function showdata()
{
	$data = Cs_crmHelpersCs_crm::sessionGetVar("data");
	$alldata = str_replace( ',',',<br .>', json_encode($data) );
	JFactory::getApplication()->enqueueMessage("Data: <br /><br />$alldata<br />",'success');
}
function search( $qs, $searchnotes = false )
{
	// only search if 2 or more records
	$nids = Cs_crmHelpersCs_crm::sessionGetVar("nids");
	if ( $nids < 2 )
		return;
/*
	$trac = array();
	
	$msg = "Searching for text ";
	$msg .= $searchnotes ? "and notes " : "";
	$msg .= "for \"$qs\"...<br />";
	JFactory::getApplication()->enqueueMessage($msg,'success');
*/	
	// start search on next record and stop searching on match or
	// after wrapping around to current id

	$idlist = Cs_crmHelpersCs_crm::sessionGetVar("idlist");
	$curid = Cs_crmHelpersCs_crm::sessionGetVar("curid");
	
	$id = $curid;
	if ( ++$id  > $nids )
		$id = 1;
	
	$tbl = Cs_crmHelpersCs_crm::getTableName("members");
	if ( $searchnotes )
	{
		$tblnotes = Cs_crmHelpersCs_crm::getTableName("notes");
		$show_username = JComponentHelper::getParams('com_cs_crm')->get('show_note_username',"0") == "1";
	}
	do
	{
		$memid = $idlist[$id-1];
//		$trac[$id] = "$memid";
		$sql = "SELECT * FROM $tbl WHERE id=$memid";
		$db = JFactory::getDBO();
		$db->setQuery($sql);
		$row = $db->loadAssoc();
		
		if ( doesMatchRecord( $row, $qs ) )
			break;
	
		if ( $searchnotes )
		{
			if ( stristr( Cs_crmHelpersCs_crm::getNotesString( $nnotes, $memid, $tblnotes, $show_username ), $qs ) !== FALSE )
				break;
		}
	
		if ( ++$id > $nids )
			$id = 1;
	
	} while ( $id != $curid );
	
	if ( $id != $curid )
	{
	//	JFactory::getApplication()->enqueueMessage("<br />Found a match at $id",'success');
		//JFactory::getApplication()->enqueueMessage("trac:<br />". str_replace(",","<br />",json_encode($trac)),'error');
		gotoRecord($id);
	}
	else 
	{
		//JFactory::getApplication()->enqueueMessage("trac:<br />". str_replace(",","<br />",json_encode($trac)),'error');
		//JFactory::getApplication()->enqueueMessage("<br />No match found.",'error');
		//Cs_crmHelpersCs_crm::sessionSetVar("last_search", "");
	}

	Cs_crmHelpersCs_crm::sessionSetVar("last_search", $qs);
	Cs_crmHelpersCs_crm::sessionSetVar("last_search_notes", $searchnotes);
}
function doesMatchRecord( $row, $qs )
{
	// special purpose field search like fld_id_123 or fld_lname_lowe
	if ( strncmp( $qs, "fld_", 4 ) == 0 )
	{
		$keys = explode( '_', $qs );
		if ( count( $keys ) == 3 )
		{
			$key = $keys[1];
			$val = $keys[2];
			// look for exact match of field
			return strcasecmp( $val, $row[$key] ) == 0;
		}
	}

	foreach( $row as $key => $val )
	{
		// exact match of a field if ( strcasecmp( $val, $qs ) == 0 )
		if ( ! ( stristr( $val, $qs ) === FALSE ) )
		{
//print "match=" .  $val . "<br>";
			return true;
		}
	} 

	return false;
}
function gotoRecord($id)
{
	if ( $id == "" || $id == 0 )
	{
	//	JFactory::getApplication()->enqueueMessage("gotoRecord(): curid is not set properly", 'warning');
		return;
	}
	Cs_crmHelpersCs_crm::sessionSetVar("curid", $id);
	$idlist = Cs_crmHelpersCs_crm::sessionGetVar("idlist");
	$memid = $idlist[$id-1];
	Cs_crmHelpersCs_crm::readDataIntoSession($memid);
}
function browse($first=false)
{
	// check if searching

	/*
	 {"qs":"jones",
	 "action":"Search",
	 "searchnotes":"1",
	 "memid":"104"}
	 */
	// ntodo: want to also be able to pass search terms in via URL (_GET)
	if ( isset($_POST["qs"]) && (!empty($_POST["qs"])) && isset($_POST["action"]) && $_POST["action"] == "Search" )
		return search( $_POST["qs"], isset($_POST["searchnotes"]) );

//JLog::add(json_encode($_POST), JLog::INFO, 'info');	

	$curid = Cs_crmHelpersCs_crm::sessionGetVar("curid");
	$nids = Cs_crmHelpersCs_crm::sessionGetVar("nids");
	
	if ( $nids == 0 )
		return;	// todo: something wrong - buttons should be disabled
	
	if (isset($_POST["first"])||$first)
		$curid = 1;
	else if (isset($_POST["next"]))
		{
			if ( ++$curid > $nids )
				$curid = 1;	// wrap-around forwards
		}
		else if (isset($_POST["previous"]))
		{
			if ( --$curid <= 0 )
				$curid = $nids;	// wrap-around backwards
		}
		else 
			return;	// todo: somthing wrong - improper browse data posted
		
	// store new pointer
	
	Cs_crmHelpersCs_crm::sessionSetVar("curid", $curid);
	
	// get data for this record and save it in the session to be shown in the view
	
	$idlist = Cs_crmHelpersCs_crm::sessionGetVar("idlist");
	$memid = $idlist[$curid-1];
	Cs_crmHelpersCs_crm::readDataIntoSession($memid);
}
function setscope( $scope, $goto_first = true )
{
	Cs_crmHelpersCs_crm::sessionSetVar("scope", $scope);
	
	// read all records in the scope from the DB and store their id's in the session
	
	// map the scope into actual statuses for the DB query
	/*
scopes: Active, Not Active, All, Active/Applied, Applied
statuses:
Removed
Not Renewed
Moved
Active
Not Renewing
Cancel Request
Duplicate
Never Paid
Applied
	 */
	$where = "";
	if ( $scope != "All")
	{
		if ( $scope == "Active/Applied")
			$where = "WHERE status='Active' OR status='Applied'";
		else if ( $scope == "Not Active")
			$where = "WHERE status!='Active'";
		else 
			$where = "WHERE status='$scope'";
	}
	$sql = "SELECT id FROM " . Cs_crmHelpersCs_crm::getTableName("members") . " $where". "ORDER BY lname,bname ASC";
	$db = JFactory::getDBO();
	$db->setQuery($sql);
	$rows = $db->loadAssocList();
	$nids = count($rows);
	Cs_crmHelpersCs_crm::sessionSetVar("nids",$nids);
	$idlist = array();
	for ( $i = 0 ; $i < $nids ; $i++ )
		$idlist[$i] = $rows[$i]["id"];
	Cs_crmHelpersCs_crm::sessionSetVar("idlist",$idlist);
	
	if ( $goto_first )
	{
		Cs_crmHelpersCs_crm::sessionSetVar("curid",$nids==0?0:1);	// todo: should curid ever be set to zero? something else is wrong if it's used as zero - will lead to bad array index and an sql error
		
		if ($nids)
			browse(true);
	}
	//JLog::add("trlowe: setscope: scope=\"$scope\", nrow=\"$nrows\"", JLog::INFO, 'info');	
}

function addnew()
{
	// save current position in scope's records in case "New" is canceled
	
	Cs_crmHelpersCs_crm::sessionSetVar("save_curid",Cs_crmHelpersCs_crm::sessionGetVar("curid","1")); // todo: default to 1 or 0 or ? if not found
	
	// indicate to view creating new record
	
	Cs_crmHelpersCs_crm::sessionSetVar("curid","new");
	
	//Cs_crmHelpersCs_crm::sessionSetVar("nids","0");
	//Cs_crmHelpersCs_crm::sessionSetVar("idlist",array());
	
	// populate view with a blank new record
	
	$data = Cs_crmHelpersCs_crm::getNewRecord();
	Cs_crmHelpersCs_crm::sessionSetVar("data",$data);
}
function cancelnew()
{
	JFactory::getApplication()->enqueueMessage("Canceled adding a new record.",'warning');
	
	// restore saved position
	$curid = Cs_crmHelpersCs_crm::sessionGetVar("save_curid", "1" ); // todo: default to 1 or 0 or ? if not found 
	
	//	retrieve previous data for view to display 
	gotoRecord($curid);
}