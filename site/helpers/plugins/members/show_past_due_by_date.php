<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		// retrieve data from DB
		$today = date('Y-m-d',time());	// outputs: 2006-07-05
		$orderby = "paidthru";

		$sql = "SELECT paidthru,memtype,fname,lname FROM " . Cs_crmHelpersCs_crm::getTableName("members") . " WHERE status='Active' AND paidthru!='' AND paidthru<'$today' ORDER BY $orderby";
		$db = JFactory::getDBO();
		$db->setQuery($sql);
		$results = (array) $db->loadAssocList();

		echo "<H4>" . count($results) ." past due members as of $today</h4>";

		// import library class for displaying data in a table
		
		jimport('libcs.table.simple');
		
		// show data

		LibcsTableSimple::ShowData($results);
	}
}
?>
