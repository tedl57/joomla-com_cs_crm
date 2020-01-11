<?php // 2009-11-23

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data)
	{
		// import library class for mailman listserve actions
		
		jimport('libcs.email.mailman');
		
		$obj = new LibcsEmailMailman("com_cs_crm");

		$email = $data["email"];

		$ret = $obj->sub($email,$data["fname"],$data["lname"]);
	
		$members_listserve = JComponentHelper::getParams('com_cs_crm')->get('mailman_listserve_name');
	
		if ( $ret )
		{
			$note = "Added $email to [$members_listserve]";
			Cs_crmHelpersCs_crm::addNote($data["id"],$note);
		}
		else
		{
			$note = "Failed to add $email to [$members_listserve]";
		}
		
		JFactory::getApplication()->enqueueMessage($note, $ret ? 'success' : 'error' );
			
		return true;
	}
}
?>