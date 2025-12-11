<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		// import library class for joomla users
		
		jimport('libcs.forums.smf');
		
		$obj = new LibcsForumsSmf("SMF Check",$data,"com_cs_crm");
		
		$obj->check();
		
		return true;	

		// checks made
		// member has no cmsid set yet (still 0)
		// member has a cmsid set (but it is not a valid cms user)
		// found member via username
		// found member by cmsid
		// username is NOT standard naming convention

		/*
		$userobj = new LibcsJoomlaUsers($data);
		$by = "by ";
		if ( ! $userobj->hasId() )
		{
			JFactory::getApplication()->enqueueMessage("Member's CMSID not set yet.",'error');

			$user = $userobj->getUserByUsername();
			if ( $user === null )
			{
				JFactory::getApplication()->enqueueMessage("No Joomla User found by username either",'error');
				return true;
			}
			$by .= "username";
		}
		else
		{
			// find member's juser by cmsid
			$user = $userobj->getUserById();
			if ( $user === null )
			{
				JFactory::getApplication()->enqueueMessage("Member's CMSID is set but not a valid CMS user id.",'error');
				return true;
			}

			$by .= "ID";
		}

//		{"id":"63","name":"Rad Towe","username":"rad.towe","email":"user@example.com","password":"xxxxxxxxxxxxxxxx","usertype":"Super Administrator","block":"0","sendEmail":"0","gid":"25","registerDate":"2006-12-15 01:23:45","lastvisitDate":"2018-12-06 15:08:00","activation":"","params":"editor=tinymce\nexpired=\nexpired_time="}
		$ret = "Found Joomla User $by";
		
		JFactory::getApplication()->enqueueMessage($ret."<br />".str_replace(",","<br />",json_encode($user)),'success');
		
		return true;
		 */
	}
}
?>
