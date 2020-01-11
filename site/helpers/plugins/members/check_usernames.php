<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		// shows juser usernames that don't follow fname.lname format

		$db = JFactory::getDbo();
   		$sql = "SELECT id,username,name,email FROM #__users where username NOT LIKE '%.%'";
   		$db->setQuery($sql);
   		$users = $db->loadAssocList();
		$n_users = count( $users );

		JFactory::getApplication()->enqueueMessage("Renamed usernames: $n_users",'success');
		if ( $n_users )
			JFactory::getApplication()->enqueueMessage(json_encode($users),'success');
		
		return true;
	}
}
?>
