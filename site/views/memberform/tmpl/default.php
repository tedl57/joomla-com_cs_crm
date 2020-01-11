<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Cs_crm
 * @author     Ted Lowe <lists@creativespirits.org>
 * @copyright  2018 Ted Lowe
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

JHtml::_('behavior.keepalive');
JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidation');
JHtml::_('formbehavior.chosen', 'select');

// Load admin language file
$lang = JFactory::getLanguage();
$lang->load('com_cs_crm', JPATH_SITE);
$doc = JFactory::getDocument();
$doc->addScript(JUri::base() . 'media/com_cs_crm/js/form.js');

$user    = JFactory::getUser();

$roster = Cs_crmHelpersCs_crm::sessionGetVar("showroster",null);
if ( $roster )
{
	echo $roster;
	Cs_crmHelpersCs_crm::sessionUnsetVar("showroster");
	return;
}

/*
echo generateToken(32);
echobreak();

// Test string
$str = 'Lorem ipsum...';
// Get key
$crypto = new JCryptCipherCrypto;
$key = "AllowCronToRun!";//$crypto->generateKey();
// Crypt it. Nobody can read it without key
$crypted = $crypto->encrypt($str, $key);
echo bin2hex($crypted);
echobreak();
// Decrypt crypted string
$decrypted = $crypto->decrypt($crypted, $key);
echo $decrypted;
echobreak();

function generateToken($length)
{
	jimport('joomla.user.helper');
	$token = JUserHelper::genRandomPassword($length);

	return $token;
}
*/
//$canEdit = Cs_crmHelpersCs_crm::canUserEdit($this->item, $user);
//$canSave = true;

// render the form in a custom way driven by the XML definitions

//$uri = JUri::getInstance();
//JLog::add("view: uri=$uri", JLog::INFO, 'info');
//echo "<pre>";debug_print_backtrace();echo "</pre>";

//$theModel = JModelLegacy::getInstance('memberform', 'Cs_crmModel');
//$form = $theModel->getForm();

function echobreak() {
	echo "<br />";
}
/*
$action = JFactory::getApplication()->input->get->get('action', null, 'cmd');
echo "action in view: $action";
echobreak();
echo JUri::getInstance();	// outputs the string field
echobreak();

$uri = JUri::getInstance();
//$nuri = new JUri( $uri);

echo "<pre>";var_dump($uri);echo "</pre>";

//$nuri->delVar("action");
//echo "<pre>nuri: ";var_dump($uri);echo "</pre>";

if ( $uri->hasVar("action"))
	echo "<br />uri Has action!<br />";

$uri->delVar("action");
if ( $uri->hasVar("action"))
	echo "<br />uri STILL Has action!<br />";

$jinput = JFactory::getApplication()->input;
echo "<br />component: ". $jinput->get('option') . "<br />";
*/

renderFormFromHTML();
return;

//////////////////////////////////////////////////////////////////
// functions
//////////////////////////////////////////////////////////////////
function renderFormFromHTML()
{
	$data = array();
	$nids = Cs_crmHelpersCs_crm::sessionGetVar("nids");
	$isnew = Cs_crmHelpersCs_crm::sessionGetVar("curid") == "new";
		
	if (!Cs_crmHelpersCs_crm::isUserAuth("view"))	// authtodo:
		return;
	
	// authtodo: only allow membership manager (or authorized users) to see the New button

	renderAppHeader();
	renderFormStyles();
	if (!$isnew)
	{
		renderFormNavSearchBrowse( $data );
		renderFormNavScope();
	}
	if ( $nids != 0 || $isnew )
	{
		renderFormMemberInfo( $isnew, $data );
		
		if (!$isnew)
		{
			if ( Cs_crmHelpersCs_crm::isMemDB() )
			{
				$showform = Cs_crmHelpersCs_crm::sessionGetVar("showenterpaymentform",0) == 1;
				if ( $showform )
					renderFormEnterPayment( $data );
				Cs_crmHelpersCs_crm::sessionSetVar("showenterpaymentform",0);
			}
			
			renderFormNotes( $data["id"] );
		}
	}

	if (!$isnew)
		renderPlugins();

	renderScript();
}
function renderFormNavSearchBrowse( & $data )
{
	$nids = Cs_crmHelpersCs_crm::sessionGetVar("nids");
	$curid = Cs_crmHelpersCs_crm::sessionGetVar("curid");
	
	$disabled = $nids <= 1 ? "disabled" : "";
	
	// never show 1 of 0
	if ( $nids == 0 )
		$curid = 0;

	$current_record = "[ $curid of $nids ]&nbsp;";
	
	// set data fields if a record exists
	$memid = "";
	if ( $nids > 0 )
	{
		$data = Cs_crmHelpersCs_crm::sessionGetVar("data");
		$memid = $data["id"];
	}
	
	$uri = Cs_crmHelpersCs_crm::getURI("browse");
	$id_label = $nids > 0 ? getIdLabel( "Id:") : "Id:";
	$last_search = Cs_crmHelpersCs_crm::sessionGetVar("last_search");
	$last_search_notes = Cs_crmHelpersCs_crm::sessionGetVar("last_search_notes");
	$notes_checked = ($last_search_notes === true) ? " checked " : "";
	
	echo<<<EOT
<!-------------------- NAV: SEARCH/BROWSE --------------------------------------- -->
<div style="background-color: gainsboro;" class="grouping">
	<form action="$uri" style="display: initial;" name="browse" method="post">
    	<div class='floatsep'>
    		<input id="qs" $disabled style="width:10em; display: initial;" type="text" value="$last_search" name="qs">
    	</div>
		<div class='floatsep'>
    		<input class='button_vcenter' type="submit" $disabled style="display: initial; margin-bottom: 0.3em;" name="action" value="Search">
    	</div>
		<div class='floatsep button_vcenter'>
    		&nbsp;Notes:
			<input id="searchnotes" $disabled $notes_checked type="checkbox" style="display: initial; margin-bottom: 0.4em;" name="searchnotes" value="1">
		&nbsp;&nbsp;$id_label&nbsp;
   		</div>
		<div class='floatsep'>
			<input id="memid" style="width:4em;" readonly type="text" value="$memid" name="memid">
		</div>
&nbsp;&nbsp;$current_record
		<input type="submit" $disabled class="button_vcenter" style="display: initial; margin-bottom: 0.3em;" name="first" value="^First^">
		<input type="submit" $disabled class="button_vcenter" style="display: initial; margin-bottom: 0.3em;" name="previous" value="<Previous">
		<input type="submit" $disabled class="button_vcenter" style="display: initial; margin-bottom: 0.3em;" name="next" value="Next>">
		
	</form>
</div>
EOT;
}
function renderFormNavScope()
{
	$session = JFactory::getSession();
	$cur_scope = $session->get(Cs_crmHelpersCs_crm::whatApp().'/scope',"Active");

	$uri = Cs_crmHelpersCs_crm::getURI("addnew");
	
	echo<<<EOT
<!-------------------- NAV: SCOPE --------------------------------------- -->
<div style="background-color: gainsboro;" class="grouping">
<form style="display: initial;" name="browse" method="post" action="$uri">
		<input type="submit" class="button_vcenter" style="display: initial; margin-bottom: 0.6em;" name="new" value="New">
&nbsp;
&nbsp;
Show:
&nbsp;
EOT;
	$scopes = array( "Active", "All", "Not Active" );
	if ( Cs_crmHelpersCs_crm::isMemdb() )
		array_push($scopes, "Active/Applied", "Applied" );

	foreach( $scopes as $scope )
	{
		if ( $scope == $cur_scope )
		{
			$onclick='';
			$checked = "checked";
		}
		else 
		{
			$onclick = "onClick=\"scopeChanged('$scope')\"";
			$checked = "";
		}  
		
		echo<<<EOT
	<input type='radio' style="display: initial; margin-bottom: 0.6em;" $checked $onclick name='status_scope' value='$scope'> $scope
	&nbsp;&nbsp;&nbsp;
EOT;
	}
	
	echo<<<EOT
</form>
</div>
EOT;
}
function isAuth($item)
{
	return true;
}
function renderPlugins()
{
	if ( !isAuth("plugins"))
		return;

	$plugins = Cs_crmHelpersCs_crm::getPluginNames();

	$nplugins = count($plugins);
	if ( $nplugins == 0 )
		return;

	$uri = Cs_crmHelpersCs_crm::getURI("runplugin");
	
	echo<<<EOT
<!------------------------------------------ PLUGINS -------------------------------------- -->
<div style="background-color: Pink;" class="grouping">
	<p class="section_heading">Plugins</p>
	<form style="display: initial;" name="plugins" method="post" action="$uri">
EOT;
		// list plugins 4 across
	$i = 0;
	foreach($plugins as $name)
	{
		echo<<<EOT
			<input type="submit" name="plugin" value="$name" class="floatsep">
EOT;
		if (++$i % 4 == 0 && $i != $nplugins)	// don't clear on last row
			echo '<br style="clear:both;" />';
	}
		
	echo<<<EOT
	</form>
				
  	<br style="clear:both;" />			
</div>
EOT;
}
function renderAppHeader()
{
	$db_type = Cs_crmHelpersCs_crm::isMemDB() ? "Membership" : "Contacts";
	$href = Cs_crmHelpersCs_crm::getHrefPopup("/components/com_cs_crm/helpers/help.html","Help");
	// todo: get version # from Joomla
	echo<<<EOT
<span style='font-weight: bold;'>$db_type DB</span>&nbsp;&nbsp;	
	$href
	<br />
EOT;
}			
function renderFormStyles()
{
	echo<<<EOT
<!------------------------------------------ CSS STYLES -------------------------------------- -->
<style>
input, label, select {
    display:block;
}
label {
	margin-bottom: 2px;
}
input[type="text"]{
	margin-bottom: 3px;
	color: Black;
}
input.pastdue {
	color: red;
	font-weight: bold;			
}
input {
	font-weight: bold;			
}
select {
	width: 10em;
	font-weight: bold;
}
select.lesswide {
	width: 7em;
}
select.autowide {
	width: auto;
}
p.section_heading {
	font-weight: bold;
	color: Black;
	margin-bottom: 3px;
}
a.crmlink:visited, a.crmlink:link {
	color: black;
    text-decoration: underline;
}
div.grouping {
	margin-top, margin-bottom: 2px;
	padding-top: 2px;
	padding-bottom: 0;
	padding-left: 3px;
	border-bottom: 1px solid;
	border-width:thin;
	background-color: PaleTurquoise;
}
form {
	margin: 0;
}
.date_width {
	width: 7em;
}
.datetime_width {
	width: 11.5em;
} 		
.phone_width {
	width: 9em;
}
.button_vcenter {
margin-top: 0.3em; 	
}			
.button_vcenter_label {
margin-top: 1.85em; 	
}		
.floatsep {
	float: left;
	margin-right: 5px;
}
.floatsepmore {
	float: left;
	margin-right: 8px;
}
</style>
EOT;
}
function renderFormMemberInfo( $isnew, & $data )
{
	$memdb = Cs_crmHelpersCs_crm::isMemDB();
	
	$fname = $data["fname"];
	$lname = $data["lname"];
	$bname = $data["bname"];
	$address = $data["address"];
	$city = $data["city"];
	$state = $data["state"];
	$zip = $data["zip"];
	$email = $data["email"];
	$website = $data["website"];
	$hphone = $data["hphone"];
	$wphone = $data["wphone"];
	$cphone = $data["cphone"];
	$fax = $data["fax"];
	$mi = $data["mi"];
	$title = $data["title"];
	$paidthru = $data["paidthru"];
	$paidthruclass = isDue($paidthru) ? " pastdue" : "";
	$member_since = $data["member_since"];
	$newsletter_dist = $data["newsletter_dist"];
	$status = $data["status"];
	$source = $data["source"];
	$memtype = $data["memtype"];
	$date_entered = $data["date_entered"];
	$last_updated = $data["last_updated"];

	if ($isnew)
	{
		$button_label = "Save";
		$form_action = "savenew";
	}
	else
	{
		$button_label = "Update";
		$form_action = "update";	
	}
	$uri = Cs_crmHelpersCs_crm::getURI($form_action);	
	
	// handle website links to obey secure links
	if ( strpos($website,"http://") === 0 )
	{
		$href = $website;
	}
	else if ( strpos($website,"https://") === 0 )
	{
		$href = $website;
	}
	else
	{
		$href = "http://$website";
	}
	$website_label = empty( $website) ? "Website" : "<a class='crmlink' target=\"_blank\" href=\"$href\">Website</a>";
	
	
	$email_label = empty( $email ) ? "Email" : "<a class='crmlink' href=\"mailto:$email\">Email</a>";
	$address_label = getAddressLabel( "Street Address");
	$city_label = getMapLabel($data,"City");
	$paidthru_label = getPaidThroughLabel("Paid Through");
	
	if ( $isnew )
		echo "<p><br /><b>Adding a new record...</b></p>";
	
	echo<<<EOT
<!------------------------------------------ MEMBER INFO -------------------------------------- -->

<form name="message" method="post" action="$uri">
<div class="grouping">

  <div class="floatsep">
    <label for="name">First Name</label>
    <input id="fname" type="text" value="$fname" name="fname" size="10">
  </div>

  <div class="floatsep">
    <label for="lname">Last Name</label>
    <input id="lname" type="text" value="$lname" name="lname" size="10">
  </div>

  <div style="float:left;">
    <label for="bname">Business Name</label>
    <input id="bname" type="text" value="$bname" name="bname" size="10">
  </div>

  <br style="clear:both;" />

</div>
<div class="grouping">

  <div class="floatsep">
    <label for="address">$address_label</label>
    <input id="address" type="text" value="$address" name="address" size="35">
  </div>

  <div class="floatsep">
	  <label for="city">$city_label</label>
    <input id="city" type="text" value="$city" name="city" size="20">
  </div>

  <div class="floatsep">
    <label for="state">State</label>
    <input id="state" style="width: 3.5em;" type="text" value="$state" name="state">
  </div>

  <div style="float:left;">
    <label for="zip">Zip Code</label>
    <input style="width: 5em;" id="zip" type="text" value="$zip" name="zip">
  </div>
  <br style="clear:both;" />
</div>
<div class="grouping">
	<div class="floatsep">
		<label for="email">$email_label</label>
    	<input id="email" style="width:24em;" type="text" value="$email" name="email">
 	</div>
	<div class="floatsep">
		<label for="website">$website_label</label>
		<input id="website" style="width:24em;" type="text" value="$website" name="website" size="45">
	</div>
  	<br style="clear:both;" />
</div>
<div class="grouping">
 	<div class="floatsep">
    	<label for="hphone">Home Phone</label>
    	<input id="hphone" class="phone_width" type="text" value="$hphone" name="hphone">
	</div>
	<div class="floatsep">
    	<label for="wphone">Work Phone</label>
    	<input id="wphone" class="phone_width" type="text" value="$wphone" name="wphone">
	</div>
	<div class="floatsep">
    	<label for="cphone">Cell Phone</label>
    	<input id="cphone" class="phone_width" type="text" value="$cphone" name="cphone">
	</div>
 	<div class="floatsep">
    	<label for="fax">Fax</label>
    	<input id="fax" class="phone_width" type="text" value="$fax" name="fax">
	</div>
 	<div class="floatsep">
    	<label for="mi">MI</label>
    	<input id="mi" style="width:0.75em;" type="text" value="$mi" name="mi">
	</div>
 	<div class="floatsep">
    	<label for="title">Title</label>
    	<input id="title" style="width:4.5em;" type="text" value="$title" name="title">
	</div>
	<br style="clear:both;" />
</div>
EOT;
if ( $memdb )
{
	$options = getOptionsNewsletterDist($newsletter_dist); 
	echo<<<EOT
<div id="membership_details" class="grouping">
 	<div class="floatsepmore">
		<label for="paidthru">$paidthru_label</label>
		<input id="paidthru" class="date_width$paidthruclass" type="text" value="$paidthru" name="paidthru">
	</div>
	<div class="floatsep">
    	<label for="newsletter_dist">Newsletter Dist</label>
    	<select id="newsletter_dist" type="text" value="$newsletter_dist" name="newsletter_dist">
$options
		</select>
	</div>
EOT;
	if (!$isnew)
		echo<<<EOT
	<div class="floatsep">
    	<label for="member_since">Member Since</label>
    	<input id="member_since" class="date_width" readonly type="text" value="$member_since" name="member_since">
	</div>
	<div class="floatsep">
    	<label for="date_entered">Date Entered</label>
    	<input id="date_entered" class="datetime_width" readonly type="text" value="$date_entered" name="date_entered">
	</div>
EOT;
	echo<<<EOT
    <br style="clear:both;" />
</div>
EOT;
}

if ($isnew)
	$status = $memdb ? "Applied" : "Active";

$options_statuses = getOptionsStatuses($status);

if ( $source === null )
	$source = "EMPTY";

$options_sources = getOptionsSources($source);

if ($isnew)
	$memtype = "Individual";

$options_types = getOptionsTypes($memtype);

	echo<<<EOT
<div class="grouping">
	<div class="floatsepmore">
    	<label for="status">Status</label>
    	<select id="status" type="text" value="$status" name="status">
$options_statuses
		</select>
	</div>
	<div class="floatsepmore">
    	<label for="source">Source</label>
    	<select id="source" type="text" value="$source" name="source">
$options_sources
		</select>
	</div>
	<div class="floatsepmore">
    	<label for="memtype">Type</label>
    	<select id="memtype" type="text" value="$memtype" name="memtype">
$options_types
		</select>
	</div>
EOT;
	if (!$isnew)
		echo<<<EOT
	<div class="floatsep">
    	<label for="last_updated">Last Updated</label>
    	<input id="last_updated" style="margin-bottom: 5px;" class="datetime_width" readonly type="text" value="$last_updated" name="last_updated">
	</div>
EOT;
	echo<<<EOT
	<div class="floatsep">
		<input type="submit" style="margin-top: 20px; margin-bottom: 5px;" name="update" value="$button_label">
	</div>
EOT;
	if ( $isnew )
		echo<<<EOT
	<div class="floatsep">
		<input type="submit" style="margin-top: 20px; margin-bottom: 5px;" name="cancel" value="cancel">
	</div>
EOT;
	echo<<<EOT
	<br style="clear:both;" />
</div>
</form>
EOT;
}
function renderFormEnterPayment( & $data )
{
	$memid = $data["id"];
	$amount_paid = "";
	$payment_term = "1";
	$payment_id = "";
	$note = "";
	
	// check if an unprocessed payment has this member id and if so, pre-populate the Enter Payment form
	$sql = "SELECT * FROM #__cs_payments WHERE memid=$memid AND processed_date IS NULL";
	$db = JFactory::getDBO();
	$db->setQuery($sql);
	$row = $db->loadAssoc();
	if ( is_array($row))
	{
		$payment_id = $row["id"];
		$amount_paid = $row["amount"];
		// "payment_reason":"Individual|38|2|0|0",
		$payment_reason_array = explode("|", $row["payment_reason"]);
		$payment_term = $payment_reason_array[2];
		$note = sprintf( "IPN id# %s, %s, %s year%s",
				 $row["id"],
				$payment_reason_array[0],
				$payment_term,
				$payment_term == "1" ? "" : "s" );
	}
	
	// suggested new paid through date is "payment_term" year(s) from active member's paid through date, 
	// else "payment_term" year(s) from today (for applied and inactive)

	$npaidthru = "";
	if ($data["status"] == "Active" )
	{
		if ( !empty($data["paidthru"]))	// lifetime members don't have a date
			$npaidthru = Cs_crmHelpersCs_crm::getDateNewYear($payment_term,$data["paidthru"]);
		
		$button_title = "Renew";
	}
	else
	{
		$npaidthru = Cs_crmHelpersCs_crm::getDateNewYear($payment_term);
		$button_title = $data["status"] == "Applied" ? "Activate" : "Reactivate";
	}

	$uri = Cs_crmHelpersCs_crm::getURI("enterpayment");

	echo<<<EOT
<!-------------------- ENTER PAYMENT ---------------------------------------------- -->	
<form name="operations" method="post" action="$uri">
<div style="background-color: LightGreen;" class="grouping">
	<p class="section_heading">Enter Payment</p>			
	<div class="floatsep">
    	<label for="amount_paid">Paid</label>
    	<input id="amount_paid"  style="width:3.5em;" type="text" value="$amount_paid" name="amount_paid">
	</div>
	<div class="floatsep">
    	<label for="paid_through_new">Paid Through</label>
    	<input id="paid_through_new"  class="date_width" type="text" value="$npaidthru" name="paid_through_new">
	</div>
	<div class="floatsep">
    	<label for="payment_method">How</label>
		<select id="payment_method" class="lesswide" value="Online" name="payment_method">
			<option>Online</option>
			<option>Cash</option>
			<option>Check</option>
			<option>Other</option>
			</select>
	</div>
	<div class="floatsep">
    	<label for="where_received">Where</label>
    	<select id="where_received" class="lesswide" value="PayPal" name="where_received">
			<option>PayPal</option>
			<option>Meeting</option>
			<option>EVent</option>
			<option>PO Box</option>
			<option>EAA</option>
			<option>Other</option>
		</select>
	</div>
	<div class="floatsep">
    	<label for="payment_term">Term</label>
    	<input id="payment_term" style="width:2.5em;" type="text" value="$payment_term" name="payment_term">
	</div>
	<div class="floatsep">
    	<label for="payment_note">Note</label>
    	<input id="payment_note" style="width:12em;" type="text" value="$note" name="payment_note">
	</div>		
	<div class="floatsep">
		<input type="submit" class="button_vcenter_label" name="do_operation" value="$button_title">
	</div>
	<div style="float:left;">
		<label for="automate">Auto</label>
		<input id="automate" checked type="checkbox" style="display: initial; margin-top: 1em;" name="automate" value="1">
	</div>
  	
  	<br style="clear:both;" />
				
	<input id="memid" type="hidden" value="$memid" name="memid">
	<input id="payment_id" type="hidden" value="$payment_id" name="payment_id">
</div>			
</form>
EOT;
}
function renderFormNotes( $memid )
{
	$uri = Cs_crmHelpersCs_crm::getURI("addnote");

	// show notes list if one was just added
	$notes_list_style = "";
	$shownotes = (int) Cs_crmHelpersCs_crm::sessionGetVar("shownotes", 0);
	if ( $shownotes )
	{
		$notes_list_style = "style='display: none;'";
		Cs_crmHelpersCs_crm::sessionSetVar("shownotes", 0);
	}
	$nnotes = 0;
	$tbl_notes = Cs_crmHelpersCs_crm::getTableName("notes");
	$show_username = JComponentHelper::getParams('com_cs_crm')->get('show_note_username') == "1";
	// getNotesString( &$notecount, $id, $tbl, $show_username = false, $eol = "" ) 

	$notes_string = Cs_crmHelpersCs_crm::getNotesString( $nnotes, $memid, $tbl_notes, $show_username, "<br />" );
	//printf( "%s %s| %s<br />", $row->date, $show_username ? "| " .$row->by_username." ":"", $row->note );
	echo<<<EOT
<!-------------------- NOTES --------------------------------------- -->			
<div style="background-color: Yellow;" class="grouping">
<form name="notes" action="$uri" method="post">			
	<div id="notes_header">
		<p class="section_heading">Notes ($nnotes)</p>
	</div>
	<div>			
		<div class="floatsep">
    		<input id="note"  style="width:44em;" type="text" value="" name="note">
		</div>
		<div style="float:left;">
			<input type="submit" class="button_vcenter" name="addnote" value="Add Note">
		</div>
	</div>
	
 	<br style="clear:both;" />
	
	<input id="memid" type="hidden" value="$memid" name="memid">
	
	</form>
	<div id="notes_list" $notes_list_style>
EOT;
//	$show_username = JComponentHelper::getParams('com_cs_crm')->get('show_note_username') == "1";
//	foreach( $rows as $row)
//		printf( "%s %s| %s<br />", $row->date, $show_username ? "| " .$row->by_username." ":"", $row->note );
	
	echo<<<EOT
	$notes_string
	</div>
</div>
EOT;
}
function getPaidThroughLabel( $atext )
{
	$uri = Cs_crmHelpersCs_crm::getURI("showenterpaymentform");
	$label="<a class='crmlink' title='Show the Enter Payment form' href='$uri'>$atext</a>";
	return $label;
}
function getAddressLabel( $atext )
{
	$uri = Cs_crmHelpersCs_crm::getURI("showaddress");
	$label="<a class='crmlink' title='Show mailing label for cut and paste purposes' href='$uri'>$atext</a>";
	return $label;
}
function getIdLabel( $atext )
{
	$uri = Cs_crmHelpersCs_crm::getURI("showdata");
	$label="<a class='crmlink' title='Show complete DB record label for cut and paste purposes' href='$uri'>$atext</a>";
	return $label;
}
function getMapLabel( $data, $atext )
{
	//$uaddr = urlencode( $data["address"] . "+" . $data["city"] . "+" . $data["state"] . "+" . $data["zip"] );
	//http://maps.google.com/maps?f=q&hl=en&q=445+N+Sacramento+Blvd,+Chicago,+IL+60612&ie=UTF8&z=15&ll=41.889116,-87.701325&spn=0.016581,0.043259&om=1&iwloc=addr
	//$mapurl="https://maps.google.com/maps?f=q&hl=en&q=$uaddr&ie=UTF8";
	// new format:
	// https://www.google.com/maps/place/123+Main+St,+Kansas+City,+MO+64105/
	$mapurl = str_replace( ' ', '+', sprintf( "https://www.google.com/maps/place/%s, %s, %s %s",
		$data["address"], $data["city"], $data["state"], $data["zip"] ) );
	$label="<a class='crmlink' title='Get Map to Address' target='_blank' href='$mapurl'>$atext</a>";
	return $label;
}
function renderScript()
{
	$uri = Cs_crmHelpersCs_crm::getURI( "setscope" ) . "&actid=";
	
	echo<<<EOT
<script>
function scopeChanged(scope)
{
	window.location='$uri' + scope;
}
		
jQuery(document).ready(function () {
		jQuery("#notes_list").toggle();	
		jQuery("#notes_header").click(function(){
			jQuery("#notes_list").toggle();
		});
});
</script>	
EOT;
}

function getOptionsNewsletterDist($select)
{
	$opts = array( "Electronic", "Postal Mail", "Both", "None" );
	$ret = Cs_crmHelpersCs_crm::getSelectOption();
	foreach( $opts as $opt )
	{
		$color = $opt == "Postal Mail" || $opt == "Both" ? " style='color: red;' " : "";
		$selected = $select == $opt ? " selected" : "";
		$ret .= "\t\t<option$color$selected>$opt</option>\n";
	}
	return $ret;
}
function getOptionsStatuses($select) // todo: can combine the next 3 functions
{
	$opts = Cs_crmHelpersCs_crm::getStatuses();
	$ret = Cs_crmHelpersCs_crm::getSelectOption();
	foreach( $opts as $opt )
	{
		$selected = $select == $opt ? " selected" : "";
		$ret .= "\t\t<option$selected>$opt</option>\n";
	}
	return $ret;
}
function getOptionsSources($select)
{
	$opts = Cs_crmHelpersCs_crm::getSources();
	$ret = Cs_crmHelpersCs_crm::getSelectOption();
	foreach( $opts as $opt )
	{
		$selected = $select == $opt ? " selected" : "";
		$ret .= "\t\t<option$selected>$opt</option>\n";
	}
	return $ret;
}
function getOptionsTypes($select)
{
	$opts = Cs_crmHelpersCs_crm::getTypes();
	$ret = Cs_crmHelpersCs_crm::getSelectOption();
	foreach( $opts as $opt )
	{
		$selected = $select == $opt ? " selected" : "";
		$ret .= "\t\t<option$selected>$opt</option>\n";
	}
	return $ret;
}
function isdue( $date )
{
	if ( empty( $date ) )
		return false;

	$today = getdate();
	$chk = getdate(Cs_crmHelpersCs_crm::getUnixTimestampFromMysql($date));
	return $chk[0] <= $today[0];
}

