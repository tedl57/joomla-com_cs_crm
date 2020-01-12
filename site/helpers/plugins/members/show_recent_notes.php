<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		// retrieve data from DB
//		$today = date('Y-m-d',time());	// outputs: 2006-07-05

		$sql = "SELECT memid,id,note,date,by_username FROM " . Cs_crmHelpersCs_crm::getTableName("notes") . " ORDER BY id DESC limit 50";
		$db = JFactory::getDBO();
		$db->setQuery($sql);
		$results = $db->loadAssocList();

		// post process results to show name from memid and don't show id
		$n = count($results);
		for( $i = 0 ; $i < $n ; $i++ )
		{
			unset( $results[$i]["id"] );
			$memid = $results[$i]["memid"];
			$name = self::getNameById($memid);
			$results[$i]["memid"] = "$name ($memid)";
		}
		echo "<H4>$n recent notes (latest shown first)</h4>";

		// import library class for displaying data in a table
		
		jimport('libcs.table.simple');
		
		// show data

		LibcsTableSimple::ShowData($results);
	}
	public static function getNameById($id)
	{
		$sql = "SELECT id,fname,lname FROM " . Cs_crmHelpersCs_crm::getTableName("members") . " WHERE id=$id";
		$db = JFactory::getDBO();
		$db->setQuery($sql);
		$results = $db->loadAssocList();
		$name = "no name";
		if ( isset($results[0]))
			$name = $results[0]["fname"] . " " . $results[0]["lname"];
		return $name;
	}
}
?>
