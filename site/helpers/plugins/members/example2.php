<?php

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		// demonstrate using joomla messages to show plugin output w/ input data

		JFactory::getApplication()->enqueueMessage("Hello " . $data["fname"] . "!",'success');
		
		return true;
	}
}
?>
