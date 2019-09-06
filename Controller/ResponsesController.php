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

class ResponsesController extends AppController
{
    var $uses = array('Response', 'ArchiveResponse');
    public $components = array('CurrentRequest');
    public $helpers = array('CurrentRequest');
    
    public function isAuthorized()
    {
        return (
            !$this->CurrentUser->isSystemAdmin()
            && $this->CurrentDevice->registered()
            && $this->CurrentDevice->stateid()
            && $this->CurrentUser->hasPermission('runTransaction')
        );
    }

    public function index()
    {       
        // If we're on a mobile, redirect to the most recent request.
        if ($this->Mobile->isMobile())
        {
            if (!$this->CurrentRequest->requestId())
            {
                $result = $this->Response->Request->find('first', array(
                    'fields' => array('requestId'),
                    'conditions' => array(
                        'agencyId' => $this->CurrentAgency->agencyId,
                        'agencyDeviceId' => $this->CurrentDevice->agencyDeviceId,
                    ),
                    'order' => 'requestDate desc',
                    'limit' => 1
                ));

                if (empty($result))
                    return;
            
                $this->CurrentRequest->requestId($result['Request']['requestId']);
            }

            return $this->redirect(array('action' => 'request', $this->CurrentRequest->requestId()));
        }
        
        $this->layout = 'browser';
        
        // Determine the total number of general responses
        $generalResponse = $this->Response->Request->findGeneralRequestInfo(
                $this->CurrentAgency->agencyId,
                $this->CurrentDevice->agencyDeviceId,
                $this->CurrentUser->agencyUserId);
        $this->set('generalResponse', $generalResponse);

        // Load the currently active requests for this device.
        $request_list = $this->Response->Request->findRequestInfo(
            $this->CurrentAgency->agencyId,
            $this->CurrentDevice->agencyDeviceId
        );
        $this->set('request_list', $request_list);

        // Load the request details for the currently selected request, if any.
        $requestId = $this->CurrentRequest->requestId();
        if ($requestId === false)
            return;

        $this->loadRequest();
    }
    
    public function request($requestId=0)
    {
        $this->CurrentRequest->requestId($requestId);
        
        // If this isn't called as an AJAX function and we're not on a mobile, redirect to the index page to reload it.
        if (!$this->Mobile->isMobile() && !$this->request->is('ajax'))
            return $this->redirect(array('action' => 'index'));

        $this->loadRequest();
    }
    
    public function request_list()
    {
        // Load the currently active request
        $requestId = $this->CurrentRequest->requestId();
        if (!empty($requestId))
        {
            $request = $this->Response->Request->findRequestInfo($requestId);
            $this->set('request', $request);
        }

        // Load the list of all requests for the current device.
        $request_list = $this->Response->Request->findRequestInfo(
            $this->CurrentAgency->agencyId,
            $this->CurrentDevice->agencyDeviceId
        );
        $this->set('request_list', $request_list);
    }

    public function clear_request($requestId=null)
    {
        if (empty($requestId) && !is_numeric($requestId))
            $this->Response->Request->archive(
                $this->CurrentAgency->agencyId,
                $this->CurrentDevice->agencyDeviceId);
        else if (is_numeric($requestId) && $requestId == '0')
            $this->Response->archive(
                $this->CurrentAgency->agencyId,
                $this->CurrentDevice->agencyDeviceId);
        else
            $this->Response->Request->archive($requestId);

        $this->CurrentRequest->requestId(false);
        return $this->redirect(array('action' => 'index'));
    }

    public function clear_response($responseId)
    {
        $this->Response->archive($responseId);

        if (!$this->request->is('ajax'))
            return $this->redirect(array('action' => 'index'));
        
        exit;
    }

    public function print_general($responseId=null)
    {
        $this->layout = 'print';

        if ($responseId == null && !empty($this->request->params['named']['ids']))
            $responseId = explode(',', $this->request->params['named']['ids']);

        $this->loadRequest(true, $responseId);
    }
    
    private function loadRequest($printing=false, $responseId=null)
    {
        list($request, $responses) = $this->CurrentRequest->loadRequest(
            $printing, $responseId, $this->Mobile->isMobile());

        if (!$printing)
        {
            $this->set(compact('request', 'responses'));
        
            // Mark the pulled responses as read.
            $responseIds = Set::extract('/Response/responseId', $responses);
            if (!empty($responseIds))
                $this->Response->markRead($responseIds);
        }
        else
        {
            $request['Response'] = $responses;
            $this->set('request', $request);
        }
    }
}
