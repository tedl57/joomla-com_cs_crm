<?php // 2007-03-04

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		// import library class for mailman listserve actions
		
		jimport('libcs.email.mailman');

		$obj = new LibcsEmailMailman("com_cs_crm");
		
		$ret = $obj->who($data["email"]);
		
		// todo: process output of mailman to make it more user friendly

		if ( $ret === false )
		{
			echo "Error: " . $obj->get_last_error();
			return false;
		}
		
		echo $ret;

		return false;
	}
}
?>
