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

class ArchiveResponsesController extends AppController
{
	public $components = array('ArchiveSource',
        'Filter' => array(
            'datetime' => 'responseTime',
            'fields'   => array(
                'user', // Handled in _adjustFilter
                'generalResponses', // Handled in _adjustFilter
                'source', // Handled in _adjustFilter
                'deviceName' => array('comparison' => 'like'),
                'connectCicORI' => array('comparison' => 'like'),
                'connectCicMnemonic' => array('comparison' => 'like'),
                'responseType' => array('comparison' => 'like'),
                'responseHit' => array('comparison' => 'like'), 
                'messageData' => array('comparison' => 'like')
            ),
        )
    );
        
    public function index()
    {
        if ($this->Filter->changed())
        {
            $description = $this->Filter->description();
            AuditLog::Log(AUDIT_RH_SEARCH, $description);
        }

        $this->set('sources', $this->ArchiveSource->getSources());
        $this->set('responses', $this->getFilteredResults(25));
    }

    public function print_results()
    {
        $this->layout = 'print';
        $this->set('responses', $this->getFilteredResults());
    }
   
    public function _adjustFilter($key, $model, $filter, $conditions)
    {
        // Handle User
        if (!empty($conditions['ArchiveResponse.user']))
        {
            $user = FilterComponent::fromMask($conditions['ArchiveResponse.user']);
            unset($conditions['ArchiveResponse.user']);
            
            $conditions['AND'][] = array(
                array(
                    'OR' => array(
                        'ArchiveResponse.userName like' => $user,
                        'ArchiveResponse.userFullNameLast like' => $user
                    )
                )
            );
        }

        // Limit responses to general responses only
        if (!empty($conditions['ArchiveResponse.generalResponses']))
        {
            unset($conditions['ArchiveResponse.generalResponses']);
            $conditions[] = 'ArchiveResponse.requestId is null';
        }
        
        // Force the device name if the user doesn't have appropriate permissions.
        if (!$this->CurrentUser->hasPermission('searchResponseHistory'))
            $conditions['ArchiveResponse.deviceName'] = $this->CurrentDevice->name;

        // Handle the source
        if (!empty($filter['source']))
        {
            unset($conditions['ArchiveResponse.source']);
            $source = "CLIPS_" . $filter['source'];
            $this->ArchiveSource->setSource($source, $model);
        }

        return $conditions;
    }
    
    private function getFilteredResults($limit=0)
    {
        $options = array(
            'contain'    => array(
                'ArchiveRequest' => array('formId'),
            ),
            'fields'     => array('responseId', 'requestId', 'responseTime', 'deviceName', 'computerName', 'ipAddress',
                                  'connectCicOri', 'connectCicMnemonic', 'userName', 'userFullNameLast', 'responseType',
                                  'messageData'),
            'conditions' => array('ArchiveResponse.agencyId' => $this->CurrentAgency->agencyId),
            'order'      => array('responseTime' => 'asc'),
            'paginate'   => ($limit > 0),
        );
        
        if ($limit > 0)
            $options['limit'] = $limit;
        
        return $this->Filter->filter($options);
    }
}
