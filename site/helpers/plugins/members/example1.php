<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		// demonstate output to a non-joomla view

		echo "<p>A paragraph.</p>";
		echo "<p>and another.</p>";
		
		$uri = Cs_crmHelpersCs_crm::getURI("");	// sending blank action returns to com_cs_crm (memdb)
		jexit("<br />Plugin completed. <a href='$uri'>Return to application</a>.");
		
		return true;
	}
}
?>