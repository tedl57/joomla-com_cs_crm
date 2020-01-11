<?php

// No direct access
defined('_JEXEC') or die;

function FIXMEuserOnActivate($data,$verbose)
{
	$caller = "userOnActivate";

	// todo: add juser, add forums login, send instructions, add to [members_listserve]

	/* User ann.kramer added to Mambo CMS as ID 1039
	 * Sent instructions to Ann Kramer @ user@example.com
	 * Sent welcome email to Amy Kramer @ memberslistserve@example.com
	 */

	////////////////////////////////////////////////////////////////////
	// add Joomla user

	Cs_crmHelpersCs_crm::JoomlaUserAdd($caller,$data);

	////////////////////////////////////////////////////////////////////
	// add to [members_listserve]
	
	// import library class for mailman listserve actions
	
	jimport('libcs.email.mailman');
	
	$obj = new LibcsEmailMailman("com_cs_crm");
	
	$email = $data["email"];
	
	$ret = $obj->sub($email,$data["fname"],$data["lname"]);
	
	if ( $ret )
	{
		$note = "$caller: Added $email to [members]";

		Cs_crmHelpersCs_crm::addNote($data["id"],$note);
	}
	else
	{
		$note = "$caller: Failed to add $email to [members]";
	}
	
	JFactory::getApplication()->enqueueMessage($note, $ret ? 'success' : 'error' );
	
	/////////////////////////////////////////////////////////////////////
	// add forums login

	// todo: set new forumsid field in members table
	// todo: add note about added forums user
/*
	$user = $userobj->getUserByUsername();
	if ( $user === null )
	{
		JFactory::getApplication()->enqueueMessage("No Joomla User found",'error');
		return true;
	}
//		{"id":"63","name":"Rad Towe","username":"rad.towe","email":"rad.towe@example.com","password":"xxxxxxxxxxxxxxxxxxxxx","usertype":"Super Administrator","block":"0","sendEmail":"0","gid":"25","registerDate":"2006-12-15 01:23:45","lastvisitDate":"2018-12-06 15:08:00","activation":"","params":"editor=tinymce\nexpired=\nexpired_time="}
		$ret = "Found Joomla User";
		
*/
	$str = $data["fname"]. " ".$data["lname"];
	JFactory::getApplication()->enqueueMessage("userOnActivate: $str",'info');

	// todo:  could return true or false to tell the core code to continue or stop after this user action??? a better approach would be to have another mechanism though
}
function FIXMEuserOnDeactivate($data,$verbose)
{
	$caller = "userOnDeactivate";

	/////////////////////////////////////////////////////////////////
	// add "deactivation" note in members record
	
	$note = "Deactivated " . $data["fname"]. " ".$data["lname"];
	Cs_crmHelpersCs_crm::addNote( $data["id"], $note );
	
	// todo: ban forums user, add notes
	// todo: could email FINAL message - Sorry to see you go!!! (removing from [members] does

	/////////////////////////////////////////////////////////////////
	// block CMS user
	
	Cs_crmHelpersCs_crm::JoomlaUserBlock("userOnDeactivate",$data);
	
	if ( $verbose )
		JFactory::getApplication()->enqueueMessage("userOnDeactivate: $note",'info');
	
	/////////////////////////////////////////////////////////////////
	// remove from [members_listserve]

	$members_listserve = JComponentHelper::getParams('com_cs_crm')->get('mailman_listserve_name');
	
	// import library class for mailman listserve actions
	
	jimport('libcs.email.mailman');
	
	$obj = new LibcsEmailMailman("com_cs_crm");

	$email = $data["email"];
	
	$ret = $obj->unsub($email);
	
	if ( $ret )
	{
		$note = "$caller: Removed $email from [$members_listserve]";
		Cs_crmHelpersCs_crm::addNote($data["id"],$note);
	}
	else
	{
		$note = "$caller: Failed to remove $email from [$members_listserve]";
	}
	
	if ( $verbose )
		JFactory::getApplication()->enqueueMessage($note, $ret ? 'success' : 'error' );
}
function FIXMEuserOnReactivate($data,$verbose)
{
	$note = "Reactivated " . $data["fname"]. " ".$data["lname"];
	Cs_crmHelpersCs_crm::addNote( $data["id"], $note );

	// todo: unblock cms user, unban forums user, add notes

	Cs_crmHelpersCs_crm::JoomlaUserUnblock("userOnReactivate",$data);

	if ( $verbose )
		JFactory::getApplication()->enqueueMessage("userOnReactivate: $note",'info');
}
?>