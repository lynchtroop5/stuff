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

class CriminalHistoriesController extends AppController
{
    public $uses = array('CriminalHistory', 'ConnectCicForm');
	public $components = array('ArchiveSource', 'CriminalHistorySession', 
        'Filter' => array(
            'datetime' => 'entryDate',
            'fields' => array('source', 'criminalHistoryId', 'entryDate', 'deviceName', 
                              'ori', 'mnemonic', 'user', 'disposition')
        )
    );
    public $helpers = array('CriminalHistorySession');
    
    public function index()
    {
        if ($this->Filter->changed())
        {
            $description = $this->Filter->description();
            AuditLog::Log(AUDIT_CH_SEARCH, $description);
        }

        $this->set('sources', $this->ArchiveSource->getSources());
        $this->set('criminal_histories', $this->getFilteredResults(25));
    }

    public function print_results()
    {
        $this->layout = 'print';
        $this->set('criminal_histories', $this->getFilteredResults());
    }
    
    public function audit($requestId=null)
    {
        if ($this->request->is('post'))
        {
            if ($this->request->data['Meta']['button'] == 'Continue Session')
                $this->CriminalHistorySession->approve();
            else
            {
                if ($this->CriminalHistorySession->inSession())
                    $this->CriminalHistorySession->startNew();
                else
                {
                    $this->CriminalHistory->set($this->request->data);
                    if ($this->CriminalHistory->validates())
                        $this->CriminalHistorySession->approve($this->request->data);
                    else
                        $this->Session->setFlash('You must complete all fields to continue.');
                }
            }

            return $this->redirect(array('controller' => 'forms', 'action' => 'index', $requestId));
        }

        $form = $this->ConnectCicForm->find('first', array(
            'fields' => array(),
            'conditions' => array(
                'formId' => $this->CriminalHistorySession->form('formId')
            )
        ));

        $this->set('requestId', $requestId);        
        $this->set('form', $form['ConnectCicForm']);
    }
   
    public function _adjustFilter($key, $model, $filter, $conditions)
    {
        // Handle User
        if (!empty($conditions['CriminalHistory.user']))
        {
            $user = FilterComponent::fromMask($conditions['CriminalHistory.user']);
            unset($conditions['CriminalHistory.user']);
            
            $conditions['AND'][] = array(
                array(
                    'OR' => array(
                        'CriminalHistory.userName like' => $user,
                        'CriminalHistory.userFullNameLast like' => $user
                    )
                )
            );
        }
        
        // Force the device name if the user doesn't have appropriate permissions.
        if (!$this->CurrentUser->hasPermission('searchCriminalHistory'))
            $conditions['CriminalHistory.deviceName'] = $this->CurrentDevice->name;

        // Handle the source
        if (!empty($filter['source']))
        {
            unset($conditions['CriminalHistory.source']);
            $source = "CLIPS_" . $filter['source'];
            $this->ArchiveSource->setSource($source, $model);
        }

        return $conditions;
    }
    
    private function getFilteredResults($limit=0)
    {
        $options = array(
            'contain'    => array('ArchiveRequest' => array(
                'fields' => array('requestId', 'formId'),
                'order'  => array('criminalHistoryId', 'requestDate')
            )),
            'fields'     => array('criminalHistoryId', 'entryDate', 'deviceName', 'computerName', 'ipAddress', 'ori',
                                  'mnemonic', 'userName', 'userFullNameLast', 'disposition'),
            'conditions' => array('agencyId' => $this->CurrentAgency->agencyId),
            'order'      => array('entryDate' => 'desc'),
            'paginate'   => ($limit > 0)
        );

        if ($limit > 0)
            $options['limit'] = $limit;

        $criminalHistories = $this->Filter->filter($options);

        // CakePHP doesn't handle multi-level HABTM with hasOne relationships very well  So we have to stub in the form 
        // information manually.
        foreach($criminalHistories as $criminalHistoryIndex => $criminalHistory)
        {
            foreach($criminalHistory['ArchiveRequest'] as $requestIndex => $request)
            {
                // We can optimize out the SQL calls by just assuming that the form name is embedded in the form id
                // (because it is).
                $criminalHistories[$criminalHistoryIndex]['ArchiveRequest'][$requestIndex]['ConnectCicForm'] = array(
                    'formName' => substr($request['formId'], strrpos($request['formId'], '_') + 1)
                );
                // Remove superfluous information.
                unset($criminalHistories[$criminalHistoryIndex]['ArchiveRequest'][$requestIndex]['CriminalHistoryRequest']);
            }
        }
        
        return $criminalHistories;
    }
}
