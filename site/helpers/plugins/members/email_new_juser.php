<?php
/*
 * wrote: 1/5/20 
 * send current member (a new juser) an email inviting them to login and change their password
 */

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		// see other plugin org_email_new_jusers which sends email to all of them 

		$this->send_email($data);
		
		return true;
				
	}
	public function send_email($mem)
	{
		// import library class for email sender
		
		jimport('libcs.email.sender');

		$emailobj = new LibcsEmailSender("new_cms_user","com_cs_crm");
	
		$mem["username"] = $this->getUsername($mem["fname"],$mem["lname"]);
		$to = $mem["email"];
		$emailobj->addTo( $to, $mem["fname"] . " " . $mem["lname"]);
		$ret = $emailobj->send($mem);
		$result = 'success';
		if ( $ret )
		{
			// add note about the renewal reminder being sent
			$note = "contact: new CMS user notice emailed to $to";
			Cs_crmHelpersCs_crm::addNote( $mem["id"], $note );
		}
		else
		{
			$note = " EMAIL to $to FAILED!";
			$result = 'error';
		}

		JFactory::getApplication()->enqueueMessage($note,$result);
	}
	public function getUsername($fname,$lname)
	{
		if (empty($fname) || empty($lname))
			return null;

		return strtolower( $fname . "." . $lname);
	}
}
?>