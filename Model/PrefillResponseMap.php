<?php
/*******************************************************************************
 * Copyright 2012 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 ******************************************************************************/

class PrefillResponseMap extends AppModel
{
	public $useTable     = 'tblPrefillResponseMap';
	public $primaryKey   = 'prefillResponseMapId';

	public function beforeSave($options = array())
        {	
		// Prevent saving
		return false;
	}
	
	public function beforeDelete($cascade = true) 
	{
		// Prevent deleting
		return false;
	}

        public function getPrefillMap($interface, $system, $class)
        {
            /* @var $ds DboSource */
            $ds = $this->getDataSource();

            // We're going to build two SQL queries, and then combine them with union.
            // This first query selects all interface/system specific prefill mappings
            $query = array(
                'fields' => array(
                    'PrefillResponseMap.[connectCicTag]',
                    'PrefillResponseMap.[category]',
                    'PrefillResponseMap.[tag]',
                    'PrefillResponseMap.[index]',
                    'PrefillResponseMap.[repeat]',
                    'PrefillResponseMap.[displayRank]',
                ),
                'table' => $ds->fullTableName($this),
                'alias' => 'PrefillResponseMap',
                'limit' => null,
                'joins' => array(),
                'conditions' => array(
                    'PrefillResponseMap.interface' => $interface,
                    'PrefillResponseMap.system' => $system,
                    'PrefillResponseMap.class' => $class,
                ),
                'group' => null,
                'order' => null
            );
            $sql = $ds->buildStatement($query, $this);
            
            // This second query returns all generic prefill mappings that do not already exist
            // as an interface/system specific mapping. The association is based on target prefill value (category, 
            // tag, and index).
            $query = array(
                'fields' => array(
                    'PrefillResponseMap.[connectCicTag]',
                    'PrefillResponseMap.[category]',
                    'PrefillResponseMap.[tag]',
                    'PrefillResponseMap.[index]',
                    'PrefillResponseMap.[repeat]',
                    'PrefillResponseMap.[displayRank]',
                ),
                'table' => $ds->fullTableName($this),
                'alias' => 'PrefillResponseMap',
                'limit' => null,
                'joins' => array(
                    array(
                        'table' => $ds->fullTableName($this),
                        'alias' => 'PrefillInterfaceResponseMap',
                        'type' => 'LEFT',
                        'conditions' => array(
                            'PrefillInterfaceResponseMap.interface' => $interface,
                            'PrefillInterfaceResponseMap.system' => $system,
                            'PrefillInterfaceResponseMap.class = PrefillResponseMap.class',
                            'PrefillInterfaceResponseMap.category = PrefillResponseMap.category',
                            'PrefillInterfaceResponseMap.tag = PrefillResponseMap.tag',
                            'PrefillInterfaceResponseMap.index = PrefillResponseMap.index',
                        )
                    )
                ),
                'conditions' => array(
                    'PrefillInterfaceResponseMap.interface' => null,
                    'PrefillResponseMap.interface' => null,
                    'PrefillResponseMap.system' => null,
                    'PrefillResponseMap.class' => $class
                ),
                'group' => null,
                // Putting the order by here will result in it being added to the full query correctly.
                'order' => array('category','tag','index')
            );
            $sql.= ' UNION ' . $ds->buildStatement($query, $this);
            
            // Get the rows
            $results = $ds->fetchAll($sql, array(), 'PrefillResponseMap');
            
            // Convert the output to the CakePHP model format
            $ret = array();
            foreach($results as $row)
                $ret[][$this->alias] = $row[0];

            return $ret;
        }
}
