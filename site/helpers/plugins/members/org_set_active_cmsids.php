<?php
/*
 * for each active member without a set cmsid (0),
 * find their joomla user record to get the cmsid
 * and save it in their member record
 */

// No direct access
defined('_JEXEC') or die;

class userPlugin
{
	public function execute($data=null)
	{
		// wrote the following 12/30/19 to add cmsid to all 49 active (ONLY) members on new joomla3 website
		// see other plugin org_email_new_jusers which sends email to all of them 
		
		$sql = "SELECT id,fname,lname,email,cmsid FROM " . Cs_crmHelpersCs_crm::getTableName("members") . " WHERE status='Active'";
		$db = JFactory::getDBO();
		$db->setQuery($sql);
		$results = $db->loadAssocList();
		$nproc = 0;
		$nnoemail = 0;
		
		foreach( $results as $mem )
		{
			if ( empty($mem['email']))
			{
				$nnoemail++;
				continue;
			}
			
			if ( $mem['cmsid'] == '0' )
			{
				JFactory::getApplication()->enqueueMessage("setting cmsid for member:<br />" . str_replace('},','},<br />',json_encode($mem)),'success');
				
				Cs_crmHelpersCs_crm::JoomlaUserAdd("Juser Add",$mem);
				$nproc++;
			}
		}
		JFactory::getApplication()->enqueueMessage(count($results) . " active members found. $nnoemail have no email. Attempted to set cmsid on $nproc",'success');
		JFactory::getApplication()->enqueueMessage("active CMSID's:<br />" . str_replace('},','},<br />',json_encode($results)),'success');
		
		return true;
	}
}
?>
