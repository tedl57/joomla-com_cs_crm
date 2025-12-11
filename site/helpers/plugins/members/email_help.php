<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		$ret = true;
		$to = isset($data["email"])?$data["email"]:"";
		$note = "";
		
		if ( empty( $to ))
		{
			$ret = false;
			$note = "Failed to email help message - No email address!";
		}
		else
		{
			// import library class for email sender
		
			jimport('libcs.email.sender');

			$emailobj = new LibcsEmailSender("email_help_msg","com_cs_crm");
	
			$data["name"] = $this->getName($data);
			$data["username"] = $this->getUsername($data["fname"],$data["lname"]);
			$emailobj->addTo( $to, $data["name"]);
			$ret = $emailobj->send($data);
			if ( $ret )
			{	
				// add note about the help message being sent
				$note = "contact: emailed help message to $to";
				Cs_crmHelpersCs_crm::addNote( $data["id"], $note );
			}
			else
				$note = "EMAIL to $to FAILED!";
		}
		JFactory::getApplication()->enqueueMessage($note, $ret ? 'success' : 'error' );

		return true;
	}
	public function getUsername($fname,$lname)	// ntodo: move to email library (or crm helper)
	{
		if (empty($fname) || empty($lname))
			return null;
	
		return strtolower( $fname . "." . $lname);
	}
	public function getName($data)	// ntodo: move to email library (or crm helper)
	{
		return $data["fname"] . " " . $data["lname"];
	}
}
?>
