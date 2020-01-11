<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Cs_crm
 * @author     Ted Lowe <lists@creativespirits.org>
 * @copyright  2018 Ted Lowe
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * Methods supporting a list of Cs_crm records.
 *
 * @since  1.6
 */
class Cs_crmModelMembers extends JModelList
{
    
        
/**
	* Constructor.
	*
	* @param   array  $config  An optional associative array of configuration settings.
	*
	* @see        JController
	* @since      1.6
	*/
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.`id`',
				'lname', 'a.`lname`',
				'fname', 'a.`fname`',
				'mi', 'a.`mi`',
				'bname', 'a.`bname`',
				'title', 'a.`title`',
				'address', 'a.`address`',
				'city', 'a.`city`',
				'state', 'a.`state`',
				'zip', 'a.`zip`',
				'email', 'a.`email`',
				'date_entered', 'a.`date_entered`',
				'by_username', 'a.`by_username`',
				'paidthru', 'a.`paidthru`',
				'memtype', 'a.`memtype`',
				'status', 'a.`status`',
				'status_updated', 'a.`status_updated`',
				'last_updated', 'a.`last_updated`',
				'member_since', 'a.`member_since`',
				'website', 'a.`website`',
				'source', 'a.`source`',
				'hphone', 'a.`hphone`',
				'wphone', 'a.`wphone`',
				'cphone', 'a.`cphone`',
				'fax', 'a.`fax`',
				'newsletter_dist', 'a.`newsletter_dist`',
				'appl_source', 'a.`appl_source`',
				'appl_comments', 'a.`appl_comments`',
				'appl_duesdue', 'a.`appl_duesdue`',
				'appl_paymethod', 'a.`appl_paymethod`',
				'created_by', 'a.`created_by`',
			);
		}

		parent::__construct($config);
	}

    
        
    
        
	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   Elements order
	 * @param   string  $direction  Order direction
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initialise variables.
		$app = JFactory::getApplication('administrator');

		// Load the filter state.
		$search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
		$this->setState('filter.search', $search);

		$published = $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_published', '', 'string');
		$this->setState('filter.state', $published);

		// Load the parameters.
		$params = JComponentHelper::getParams('com_cs_crm');
		$this->setState('params', $params);

                parent::populateState('id', 'ASC');

                $start = $app->getUserStateFromRequest($this->context . '.limitstart', 'limitstart', 0, 'int');
                $limit = $app->getUserStateFromRequest($this->context . '.limit', 'limit', 0, 'int');

                if ($limit == 0)
                {
                    $limit = $app->get('list_limit', 0);
                }

                $this->setState('list.limit', $limit);
                $this->setState('list.start', $start);
        
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return   string A store id.
	 *
	 * @since    1.6
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.state');

                
                    return parent::getStoreId($id);
                
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return   JDatabaseQuery
	 *
	 * @since    1.6
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select', 'DISTINCT a.*'
			)
		);
		$query->from('`#__cs_members` AS a');
                

		// Join over the user field 'created_by'
		$query->select('`created_by`.name AS `created_by`');
		$query->join('LEFT', '#__users AS `created_by` ON `created_by`.id = a.`created_by`');
                

		// Filter by search in title
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('a.id = ' . (int) substr($search, 3));
			}
			else
			{
				$search = $db->Quote('%' . $db->escape($search, true) . '%');
				
			}
		}
                
		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering', 'id');
		$orderDirn = $this->state->get('list.direction', 'ASC');

		if ($orderCol && $orderDirn)
		{
			$query->order($db->escape($orderCol . ' ' . $orderDirn));
		}

		return $query;
	}

	/**
	 * Get an array of data items
	 *
	 * @return mixed Array of data items on success, false on failure.
	 */
	public function getItems()
	{
		$items = parent::getItems();
                

		return $items;
	}
}
