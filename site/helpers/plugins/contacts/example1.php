<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		JFactory::getApplication()->enqueueMessage("contacts example1",'success');
		
		return true;
	}
}
?>
