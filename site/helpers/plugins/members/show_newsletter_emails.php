<?php // 2020-01-05

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		$sql = "SELECT id,bname,fname,lname,email FROM `#__cs_members` WHERE status='Active' AND ( newsletter_dist='Electronic' OR newsletter_dist='Both' ) AND `email` != '' ORDER BY lname";
		$db = JFactory::getDBO();
		$db->setQuery($sql);
		$results = (array) $db->loadAssocList();
		
		foreach($results as $res)
		{
			if ( empty( $res["fname"] ) && empty( $res["lname"] ) )
				// use bname w/o commas
				$ret =  str_replace(",","",$res["bname"]) . " &lt;" . $res["email"] . "&gt;" . "<br/>";
			else
				$ret = $res["fname"] . "&nbsp;" . $res["lname"] . " &lt;" . $res["email"] . "&gt;" . "<br />";
			echo "$ret";
		}
		
		echo "<br />" . count($results) . " email addresses<br />";
		$uri = Cs_crmHelpersCs_crm::getURI("");	// sending blank action returns to com_cs_crm (memdb)
		jexit("<br />Plugin completed. <a href='$uri'>Return to application</a>.");
	}
}

?>
