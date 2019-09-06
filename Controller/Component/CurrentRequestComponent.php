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
App::uses('Component', 'Controller');
App::uses('CurrentRequest', 'Model/Datasource');

class CurrentRequestComponent extends Component
{
    public $components = array('CurrentAgency', 'CurrentUser', 'CurrentDevice');

    private $controller = null;

	public function initialize(Controller $controller) 
    {
        $this->controller = $controller;
    }
    
    public function requestId($requestId=null)
    {
        return CurrentRequest::requestId($requestId);
    }

    public function loadRequest($printing=false, $responseId=null, $isMobile=false)
    {
        if (($requestId = $this->requestId()) === false)
            return false;
        
        if ($requestId <= 0)
            return $this->loadGeneralResponseData($printing, $responseId, $isMobile);
        
        return $this->loadCurrentRequestData($printing, $responseId, $isMobile);
    }

    private function loadGeneralResponseData($printing, $responseId, $isMobile)
    {
        $agencyId = $this->CurrentAgency->agencyId;
        $agencyUserId = $this->CurrentUser->agencyUserId;
        $agencyDeviceId = $this->CurrentDevice->agencyDeviceId;

        $rm = ClassRegistry::init('Request');
        $request = $rm->findGeneralRequestInfo($agencyId, $agencyDeviceId, $agencyUserId);

        $responses = $this->loadResponses($rm->Response, array(
            'Response.agencyId' => $this->CurrentAgency->agencyId,
            'Response.agencyDeviceId' => $this->CurrentDevice->agencyDeviceId,
            'Response.requestId' => null
        ), $printing, $responseId, $isMobile);

        return array($request, $responses);
    }
    
    private function loadCurrentRequestData($printing, $responseId, $isMobile)
    {
        $requestId = $this->requestId();

        $rm = ClassRegistry::init('Request');
        $request = $rm->findRequestInfo($requestId);

        $responses = $this->loadResponses($rm->Response, array(
            'Response.requestId' => $requestId
        ), $printing, $responseId, $isMobile);

        return array($request, $responses);
    }

    private function loadResponses($responseObject, $conditions, $printing, $responseId, $isMobile)
    {
        if (!$printing)
        {
            // HACK: Set the paging count to a rediculously high number to avoid paging mobile responses
            $items = (($isMobile == false) 
                ? Configure::read('Clips.agency.responseCount')
                : 10000);
            
            $paginator = $this->_Collection->load('Paginator', array(
                'contain' => array('Agency','ResponseValue','ResponseDrilldown', 'ArchiveImage'),
                'conditions' => $conditions,
                'limit' => $items,
                'maxLimit' => !$isMobile ? 100 : 25
            ));
            
            $results = $paginator->paginate($responseObject);
        
            $paging = $this->controller->request['paging']['Response'];
            CurrentRequest::paging($paging['page'], $paging['pageCount'], $paging['limit'], $paging['count']);
        }
        else
        {
            if ($responseId)
                $conditions['responseId'] = $responseId;

            $options = array(
                'contain' => array('Agency','ArchiveImage'),
                'conditions' => $conditions
            );
            
            $results = $responseObject->find('all', $options);
            foreach($results as $index => $data)
            {
                $data['Response']['ArchiveImage'] = $data['ArchiveImage'];
                unset($data['ArchiveImage']);
                $results[$index] = $data['Response'];
            }
        }

        return $results;
    }
}
