<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Cs_crm
 * @author     Ted Lowe <lists@creativespirits.org>
 * @copyright  2018 Ted Lowe
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

JLoader::register('Cs_crmHelper', JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_cs_crm' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'cs_crm.php');

/**
 * Class Cs_crmFrontendHelper
 *
 * @since  1.6
 */
class Cs_crmHelpersCs_crm
{
	const DEFAULT_SELECT = '-Select-';
	const DEFAULT_SCOPE = 'Active';

	public static function appBoot() // app boot
	{
		
	}
	public static function isUserAuth($action)
	{
		// authtodo:
		$user   = JFactory::getUser();

		// check if guest user (not logged in)
		if ( $user->id == 0 ) 
		{
			if ( $action == "dorenewalreminders")	// not logged in cron can run this action
				return true;
			return false;
		}

		// all registered users (logged in members) have complete authority in the Contacts DB
		
		if ( ! self::isMemDB() )
			return true;

		// get membership manager username(s)
		// todo: handle comman-separated array of usernames
				
		$mem_mgr_users = JComponentHelper::getParams('com_cs_crm')->get('mem_mgr_users');

// testing: JFactory::getApplication()->enqueueMessage("isuserauth($action): user id #".$user->id.", username: " . $user->username . ", mgr: $mem_mgr_users", 'info');
		
		$isManager = $user->username == $mem_mgr_users;	// todo: support array
		
		// managers can do all actions
		if ( $isManager )
			return true;
		
		// non-managers can only do "read-only" actions
		switch( $action )
		{
		case "setscope":
		case "showroster":
		case "gotomember":
		case "browse":
		case "showaddress":
		case "showdata":
		case "view":
			return true;
		}
		
		// todo: could put out message saying "Only a membership manager can update these records."
		return false;
	}
	public static function ClearCMSID($memid)
	{
		self::updateRecordFromArray( $memid, array("cmsid" => "0" ));
		
		$note = "Cleared CMSID";
		self::addNote($memid,$note);
		JFactory::getApplication()->enqueueMessage($note,'success');
	}
	public static function JoomlaUserAdd($caller,$data)
	{	
		// import library class for joomla users
		jimport('libcs.joomla.users');
		
		$userobj = new LibcsJoomlaUsers($data);
		if (! $userobj->addJoomlaUser($data["email"], $data["fname"]. " " . $data["lname"]) )
		{
			//JFactory::getApplication()->enqueueMessage("new user:".str_replace(",",",<br />",json_encode($user)),'success');
			JFactory::getApplication()->enqueueMessage("$caller: Error adding Joomla User: " . $userobj->get_last_error(),'error');
		}
		else
		{
			// update the member record with the new CMSID add a note to track the change 
		
			self::updateRecordFromArray( $data["id"], array("cmsid" => $userobj->getUserId()) );
		
			$note = "$caller: Added Joomla User #" . $userobj->getUserId();
			self::addNote($data["id"],$note);
			JFactory::getApplication()->enqueueMessage($note,'success');
		}
	}
	public static function JoomlaUserBlock($caller,$data)
	{
		$memid = $data["id"];

		// import library class for joomla users
	
		jimport('libcs.joomla.users');
	
		// checks made
		// member has a cmsid set
		// juser not already blocked
	
		$userobj = new LibcsJoomlaUsers($data);
	
		if ( ! $userobj->hasId() )
		{
			$note = "$caller: Member's CMSID not set yet.";
			JFactory::getApplication()->enqueueMessage($note,'error');
			self::addNote($memid,$note);
			return true;
		}
	
		$user = $userobj->getUserById();
		if ( $user === null )
		{
			$note = "$caller: Member's CMSID is set but not a valid CMS user id.";
			JFactory::getApplication()->enqueueMessage($note,'error');
			self::addNote($memid,$note);
			return true;
		}
	
		if ( $user["block"] )
		{
			$note = "$caller: Member's Joomla User account is already blocked";
			JFactory::getApplication()->enqueueMessage( $note, 'error');
			self::addNote($memid,$note);
			return true;
		}
	
		// write user data back to DB
		$userobj->saveUserById($user["id"], array( "block" => "1"));
	
		// add note to members table
		$note = "$caller: Juser blocked";
		JFactory::getApplication()->enqueueMessage( $note, 'success');
		self::addNote($memid,$note);
		return true;
	}
	public static function JoomlaUserUnblock($caller,$data)
	{
		// import library class for joomla users
	
		jimport('libcs.joomla.users');
	
		// checks made
		// member has a cmsid set
		// juser is blocked
	
		$userobj = new LibcsJoomlaUsers($data);
	
		if ( ! $userobj->hasId() )
		{
			JFactory::getApplication()->enqueueMessage("Member's CMSID not set yet.",'error');
	
			return true;
		}
	
		$user = $userobj->getUserById();
		if ( $user === null )
		{
			JFactory::getApplication()->enqueueMessage("Member's CMSID is set but not a valid CMS user id.",'error');
			return true;
		}
	
		if ( $user["block"] == "0")
		{
			JFactory::getApplication()->enqueueMessage( "Member's Joomla User account is not blocked", 'error');
	
			return true;
		}
	
		// write user data back to DB
		$userobj->saveUserById($user["id"], array( "block" => "0"));
	
		// add note to members table
		$note = "$caller: Juser: unblocked";
		self::addNote($data["id"],$note);
	
		JFactory::getApplication()->enqueueMessage( $note, 'success');
	
		return true;
	}
	public static function updateRecordFromArray($id, $arr)
	{
		// update members table, add note, re-read data into session
	
		$obj = new stdClass();
		$obj->id = $id;
		$obj->last_updated = date('Y-m-d H:i:s');
		$note = "update: ";
		foreach ( $arr as $k => $v )
		{
			$obj->$k = $v;
			$note .= "$k=$v,";
		}
		$result = JFactory::getDbo()->updateObject(self::getTableName("members"), $obj, 'id');
		self::addNote($id,$note);
		self::readDataIntoSession($id);
	}
	
	public static function getHrefPopup($url,$text)
	{
		return "<a class='crmlink' href='' onclick=\"javascript: window.open('$url','','toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=780,height=550&'); return false\">$text</a>";
	}
	public static function getDateNewYear( $yeardiff = 0, $curdate = "" )
	{
		if ( empty( $curdate ) )
			$now = getdate();
		else
			$now = getdate(self::getUnixTimestampFromMysql($curdate));
	
		return sprintf( "%04d-%02d-%02d",
				$now["year"]+$yeardiff,
				$now["mon"],
				$now["mday"] );
	}
	public static function getUnixTimestampFromMysql( $date )
	{
		// handle both yyyy-mm-dd and yyyy-mm-dd hh:mm:ss input formats
		$b = explode( ' ', $date );
		$a = explode( '-', $b[0] );
		if ( count( $b ) > 1 )
		{
			// has time component
			$c = explode( ':', $b[1] );
			return mktime( $c[0],$c[1],$c[2],$a[1],$a[2],$a[0]);
		}
	
		// no time component
		return mktime( 0,0,0,$a[1],$a[2],$a[0]);
	}
	public static function getNewRecord()
	{
		$data = array();
		
		$flds = array( "fname", "lname", "bname", 
				"address", "city", "state", "zip", 
				"email", "website", 
				"hphone", "wphone", "cphone", "fax", "mi", "title", 
				"paidthru", "member_since", 
				"newsletter_dist", "status", "source", "memtype", 
				"date_entered", "last_updated" );
		
		foreach( $flds as $fld )
			$data[$fld] = "";
		
		return $data;
	}

	// used in form (call with eol=<br />and in search
	
	public static function getNotesString( &$notecount, $id, $tbl, $show_username = false, $eol = "" ) 
	{
		// obey 'show by username in notes' param (can search for dale.corel for example
		$sql = "SELECT date,by_username,note FROM $tbl WHERE memid=$id ORDER BY id DESC";
		$db = JFactory::getDBO();
		$db->setQuery($sql);
		$rows = $db->loadObjectList();
		$notecount = count($rows);
		
		$ret = "";
		foreach( $rows as $row)
		{
			$ret .= $row->date . " | ";
			if ( $show_username )
				$ret .= $row->by_username . " | ";
			$ret .= $row->note . "$eol";
		}
		return $ret;
	}
	public static function readDataIntoSession($memid)
	{
		if ( $memid == "" )
		{
	//		JFactory::getApplication()->enqueueMessage("readDataIntoSession(): memid is not set properly", 'warning');
			return;
		}
		
		$sql = "SELECT * FROM " . self::getTableName("members") . " WHERE id=$memid";
		$db = JFactory::getDBO();
		$db->setQuery($sql);
		$row = $db->loadAssoc();
		//JLog::add(json_encode($data), JLog::INFO, 'info');
		self::sessionSetVar("data", $row);
	}
	public static function addNote( $id, $note )
	{
		// insert new note record into the db linked to the member/contact record
		
		$obj = new stdClass();
		$obj->id = 0;			// will be replaced with new auto_increment id after insert
		$obj->memid = $id;
		$obj->note = $note;
		$obj->by_username = JFactory::getUser()->get('username');
		if ( empty($obj->by_username))	// note may be added by a cron job
			$obj->by_username = "auto";
		$obj->date = date('Y-m-d H:i:s', time() );

		$result = JFactory::getDbo()->insertObject( self::getTableName("notes"), $obj, 'id');
		
		// set session variable to show the just added note in the view
		
		self::sessionSetVar("shownotes", 1);
	}
	public static function getSelectOption()
	{
		return "\t\t<option>" . self::DEFAULT_SELECT . "</option>\n";
	}
	
	/**
	 * Get an instance of the named model
	 *
	 * @param   string  $name  Model name
	 *
	 * @return null|object
	 */
	public static function getModel($name)
	{
		$model = null;

		// If the file exists, let's
		if (file_exists(JPATH_SITE . '/components/com_cs_crm/models/' . strtolower($name) . '.php'))
		{
			require_once JPATH_SITE . '/components/com_cs_crm/models/' . strtolower($name) . '.php';
			$model = JModelLegacy::getInstance($name, 'Cs_crmModel');
		}

		return $model;
	}

	/**
	 * Gets the files attached to an item
	 *
	 * @param   int     $pk     The item's id
	 *
	 * @param   string  $table  The table's name
	 *
	 * @param   string  $field  The field's name
	 *
	 * @return  array  The files
	 */
	public static function getFiles($pk, $table, $field)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query
			->select($field)
			->from($table)
			->where('id = ' . (int) $pk);

		$db->setQuery($query);

		return explode(',', $db->loadResult());
	}

	public static function getPluginFile($name)
	{
		$app = self::isMemDB() ? "members" : "contacts";

		$plugin_directory = JComponentHelper::getParams('com_cs_crm')->get("plugin_directory","");
	
		if ( empty($plugin_directory))
			return null;
		
		$plugins = self::getPluginNames();
		
		if ( $plugins === null )
			return null;
		
		foreach ( $plugins as $plugin )
			if ( $plugin == $name )
			{
				$filename = str_replace(" ", "_", strtolower($plugin) ) . ".php";
			
				return "components/com_cs_crm/$plugin_directory/$app/$filename";
			} 

		return null;
	}

	public static function getPluginNames()	// todo: put something like this in libcs except for the "members"/"contacts" stuff
	{
		$pluginparm = ( self::isMemDB() ? "members" : "contacts" ) . "_plugin_definitions";
		$plugindefs = JComponentHelper::getParams('com_cs_crm')->get($pluginparm,"");

		if (empty($plugindefs))
			return array();	// return empty array
	
		$plugins = explode(",",$plugindefs);

		$ret = array();

		foreach( $plugins as $plugin )
		{
			$plugin = trim($plugin);

			if ( $plugin == "")	// handle comma at end of list
				continue;
			$ret[] = $plugin;
		}
		
		// return empty array if none found

		return $ret;
	}
    /**
     * Gets the edit permission for an user
     *
     * @param   mixed  $item  The item
     *
     * @return  bool
     */
    public static function canUserEdit($item)
    {
        $permission = false;
        $user       = JFactory::getUser();

        if ($user->authorise('core.edit', 'com_cs_crm'))
        {
            $permission = true;
        }
        else
        {
            if (isset($item->created_by))
            {
                if ($user->authorise('core.edit.own', 'com_cs_crm') && $item->created_by == $user->id)
                {
                    $permission = true;
                }
            }
            else
            {
                $permission = true;
            }
        }
        return $permission;
    }
    public static function whatApp()
    {
    	// returns either "m" or "c" depending on Membership DB or Contacts DB
    	
    	$app = JFactory::getApplication()->input->get->get('app', "m", 'cmd');
    	return $app == "c" ? "c" : "m";
    }

    public static function isMemDB()
    {
    	return self::whatApp() == "m";
    }
    public static function sessionGetVar( $var, $def=null, $typ=null )
    {
    	$session = JFactory::getSession();
    	if ( $typ === null)
    		return $session->get(self::whatApp()."/$var", $def);
    	else
    		return $session->get(self::whatApp()."/$var", $def, $typ);
    }
	public static function sessionUnsetVar($var)
	{
		$session = JFactory::getSession();
		$session->clear(self::whatApp()."/$var");
	}
    public static function sessionSetVar( $var, $val )
    {
	    $session = JFactory::getSession();
    	$session->set(self::whatApp()."/$var", $val);
    }
    public static function getTableName( $table )
    {
    	switch( $table )
    	{
    	case 'members':
    		$end = "";
    		break;
  		case 'statuses':
   		case 'sources':
    	case 'notes':
    	case 'types':
    		$end = "_${table}";
    		break;
    	default:
    		$end = "_${table}_ERROR";
    		break;
    	}
    	
    	$pre = self::whatApp() == "m" ?	"members" : "contacts";
    	 
    	return "#__cs_" . "$pre$end";
    }
    public static function getURI( $action, $actid=null)
    {
    	$app = "";
    	if ( self::whatApp() == "c" )
    		$app = "&app=c";

    	$end = ( $actid == null ) ? "" : "&actid=$actid";
    	// ntodo: IMPORTANT - this doesn't work in a subfolder, ie, https://localhost/joomla39/index.php? 
    	$ret = "/index.php?option=com_cs_crm$app&action=$action$end";

//JLog::add("trlowe: getURI: uri=\"$ret\"", JLog::INFO, 'info');
    	
    	return $ret;
    }
    public static function getStatuses()
    {
		// get list of status, either from SESSION (cache) or DB (first time)

    	$statuses = self::sessionGetVar("statuses",null);
    	if ( $statuses === null )
    	{	
    		// else read DB and store in SESSION
    		$statuses = array();    	
    		$sql = "SELECT status FROM " . Cs_crmHelpersCs_crm::getTableName("statuses") . " ORDER BY status ASC";
	    	$db = JFactory::getDBO();
    		$db->setQuery($sql);
    		$rows = $db->loadAssocList();
			
			foreach( $rows as $row )
				$statuses[] = $row["status"];
//JLog::add("read status from DB:".json_encode($status), JLog::INFO, 'info');
			self::sessionSetVar("statuses", $statuses);
    	}
  //  	else
  //	JLog::add("read status from SESSION:".json_encode($status), JLog::INFO, 'info');
		return $statuses;
    }
    public static function getSources()
    {
		// get list of status, either from SESSION (cache) or DB (first time)

    	$sources = self::sessionGetVar("sources",null);
    	if ( $sources === null )
    	{	
    		// else read DB and store in SESSION
    		$sources = array();    	
    		$sql = "SELECT source FROM " . Cs_crmHelpersCs_crm::getTableName("sources") . " ORDER BY source ASC";
	    	$db = JFactory::getDBO();
    		$db->setQuery($sql);
    		$rows = $db->loadAssocList();
			
			foreach( $rows as $row )
				$sources[] = $row["source"];
//JLog::add("read status from DB:".json_encode($status), JLog::INFO, 'info');
			self::sessionSetVar("sources", $sources);
    	}
  //  	else
  //	JLog::add("read status from SESSION:".json_encode($status), JLog::INFO, 'info');
		return $sources;
    }
    public static function getTypes()
    {
		// get list of status, either from SESSION (cache) or DB (first time)

    	$types = self::sessionGetVar("types",null);
    	if ( $types === null )
    	{	
    		// else read DB and store in SESSION
    		$types = array();    	
    		$sql = "SELECT typ FROM " . Cs_crmHelpersCs_crm::getTableName("types") . " ORDER BY typ ASC";
	    	$db = JFactory::getDBO();
    		$db->setQuery($sql);
    		$rows = $db->loadAssocList();
			
			foreach( $rows as $row )
				$types[] = $row["typ"];
//JLog::add("read status from DB:".json_encode($status), JLog::INFO, 'info');
			self::sessionSetVar("types", $types);
    	}
  //  	else
  //	JLog::add("read status from SESSION:".json_encode($status), JLog::INFO, 'info');
		return $types;
    }
}
