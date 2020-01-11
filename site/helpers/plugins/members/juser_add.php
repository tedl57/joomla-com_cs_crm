<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		Cs_crmHelpersCs_crm::JoomlaUserAdd("Juser Add",$data);
		
		return true;
	}
}