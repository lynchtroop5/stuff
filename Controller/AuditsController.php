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

class AuditsController extends AppController
{
    public $components = array('Filter' => array(
        'fields' => array(
            'agencyId',
            'type', 
            'deviceName' => array('comparison' => 'like'),
            'ipAddress' => array('comparison' => 'like'), 
            'computerName' => array('comparison' => 'like'), 
            'user', 
            'description' => array('comparison' => 'like'))
    ));

    public function admin_index()
    {
        // Get the event types available
        $eventTypes = $this->Audit->eventTypes();
        $this->set('eventTypes', array_combine($eventTypes, $eventTypes));

        // Get a list of agencies
        $agencies = $this->Audit->Agency->find('list', array('conditions' => array(
            'agencyId' => $this->CurrentAgency->agencyId,
        )));
        $this->set('agencies', $agencies);
        
        // Get a list of audit events.
        $this->set('audits', $this->getFilteredResults($this->CurrentAgency->agencyId, 25));
    }
    
    public function admin_print_results()
    {
        $this->layout = 'print';
        $this->set('audits', $this->getFilteredResults($this->CurrentAgency->agencyId));
    }
    
	public function system_index()
	{
        // Get the event types available
        $eventTypes = $this->Audit->eventTypes();
        $this->set('eventTypes', array_combine($eventTypes, $eventTypes));

        // Get a list of agencies
        $agencies = array(-1 => '< Systems Administrator > ') +
            $this->Audit->Agency->find('list');
        $this->set('agencies', $agencies);
        
        // Get a list of audit events.
        $this->set('audits', $this->getFilteredResults(null, 25));
	}
    
    public function system_print_results()
    {
        $this->layout = 'print';
        $this->set('audits', $this->getFilteredResults());
    }
   
    public function _adjustFilter($key, $model, $filter, $conditions)
    {
        // Handle User
        if (!empty($conditions['Audit.user']))
        {
            $user = FilterComponent::fromMask($conditions['Audit.user']);
            unset($conditions['Audit.user']);
            
            $conditions['AND'][] = array(
                array(
                    'OR' => array(
                        'userName like' => $user,
                        'userFullNameLast like' => $user
                    )
                )
            );
        }
        
        return $conditions;
    }

    private function getFilteredResults($agencyId=null, $limit=0)
    {
        $options = array(
            'contain'    => array('Agency.agencyName'),
            'order'      => array('Audit.created' => 'desc'),
            'paginate'   => ($limit > 0)
        );
        
        if ($limit > 0)
            $options['limit'] = $limit;
        
        if ($agencyId !== null)
            $options['conditions'] = array('Audit.agencyId' => $this->CurrentAgency->agencyId);

        return $this->Filter->filter($options);
    }
}
