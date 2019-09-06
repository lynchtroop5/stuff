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

class RequestsController extends AppController
{
    public $uses = array('Request', 'Response', 'CurrentRequest', 'MessageQueue');
    public $components = array('CurrentRequest');
    public $helpers = array('CurrentRequest');

    public function index()
    {
        if (!$this->Mobile->isMobile())
            throw new MethodNotAllowedException();

        // Load the currently active requests for this device.
        $request_list = $this->Request->findRequestInfo(
            $this->CurrentAgency->agencyId,
            $this->CurrentDevice->agencyDeviceId
        );
        $this->set('request_list', $request_list);
    }
    
    public function drilldown($drilldownId)
    {
        $data = $this->Request->Response->ResponseDrilldown->read(null, $drilldownId);
        if (!empty($data)) {
            // TODO: this is duplicate code from FormsController.  That code should be pulled into this controller.
            $request = array(
                'LawEnforcementTransaction' => array(
                    'Session' => array(
                        'Authentication' => array(
                            'ORI' => $this->CurrentDevice->ori,
                            'UserAlias' => $this->CurrentUser->userAlias,
                            'DeviceAlias' => $this->CurrentDevice->deviceAlias,
                            'ClipsDeviceName' => $this->CurrentDevice->name
                        ),
                        'Id' => $this->CurrentDevice->deviceAlias
                    ),
                    'Transaction' => array(
                        'Request' => array(
                            'Id' => null, // Will be filled in later.
                            'MessageType' => 'DrilldownTransaction',
                            'DrilldownId' => $data['ResponseDrilldown']['connectCicId']
                        )
                    )
                )
            );

            if ($this->Request->transaction(null, true))
            {
                $this->Request->clear();
                $success = $this->Request->saveRequest(array(
                    'Request' => array(
                        'agencyId' => $this->CurrentAgency->agencyId,
                        'requestDate' => date('Y-m-d H:i:s'),
                        'agencyDeviceId' => $this->CurrentDevice->agencyDeviceId,
                        'deviceName' => $this->CurrentDevice->name,
                        'computerName' => $this->CurrentDevice->computerName,
                        'ipAddress' => $this->request->clientIp(),
                        'connectCicMnemonic' => $this->CurrentDevice->mnemonic,
                        'connectCicORI' => $this->CurrentDevice->ori,
                        'enteredORI' => $this->CurrentDevice->ori,
                        'userName' => $this->CurrentUser->userName,
                        'userLastName' => $this->CurrentUser->userLastName,
                        'userFirstName' => $this->CurrentUser->userFirstName,
                        'formTitle' => 'Drilldown Transaction',
                        'connectCicMessageType' => 'Drilldown Transaction',
                        'requestFormText' => '',
                        'requestFormNotes' => '',
                        'requestIsNew' => true
                    ),
                    'RequestValue' => array(
                        array(
                            'connectCicTag' => 'DrilldownId',
                            'connectCicTagValue' => $data['ResponseDrilldown']['connectCicId']
                        )
                    )
                ));

                if ($success)
                {
                    // Store the Request ID in the XML
                    $request['LawEnforcementTransaction']['Transaction']['Request']['Id'] = $this->Request->id;

                    // Generate the XML to submit to ConnectCIC
                    $dom = Xml::build($request, array('return' => 'domdocument'));
                    $dom->preserveWhiteSpace = false;
                    $dom->formatOutput = true;

                    // Store the request in the message queue
                    $this->MessageQueue->clear();
                    $this->MessageQueue->set(array(
                        'mailbox' => $this->CurrentDevice->deviceAlias,
                        'message' => $dom->saveXml(),
                        'processState' => 'Queued',
                        'createDate' => date('Y-m-d H:i:s'),
                    ));

                    $data = $this->MessageQueue->save();
                    $success = !empty($data);
                }

                $this->Request->transaction($success, true);
            }

            if (!$this->request->is('ajax'))
                return $this->redirect($this->referer());

            $this->set('requestId', $this->Request->id);
            exit;
        }
    }

    // The following functions are used by mobile only 
    public function clear_request_mobile($requestId=null)
    { 
        if (empty($requestId) && !is_numeric($requestId)) {
                $this->Response->Request->archive(
                $this->CurrentAgency->agencyId,
                $this->CurrentDevice->agencyDeviceId);
            }
        else if (is_numeric($requestId) && $requestId == '0') {
                $this->Response->archive(
                $this->CurrentAgency->agencyId,
                $this->CurrentDevice->agencyDeviceId);
            }
        else {
            $this->Response->Request->archive($requestId);
        }
        $this->CurrentRequest->requestId(false);
        return $this->redirect(array('action' => 'index'));
    }

    public function clear_response_mobile($responseId)
    {
        $this->Response->archive($responseId);

        if (!$this->request->is('ajax'))
            return $this->redirect($this->referer());
        exit;
    }
    // End mobile functions
}
