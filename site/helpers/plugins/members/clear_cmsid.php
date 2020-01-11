<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data)
	{
		Cs_crmHelpersCs_crm::ClearCMSID($data["id"]);
		
		return true;
	}
}