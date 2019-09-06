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

// Represents the archived version of a Request.
class ArchiveRequest extends AppModel
{
    //public $actsAs = array('RequestDetails');

	public $useTable     = 'tblArchiveRequest';
	public $primaryKey   = 'requestId';
    public $virtualFields = array(
        'userFullNameLast' => 'ArchiveRequest.userLastName + \', \' + ArchiveRequest.userFirstName'
    );

    public $belongsTo = array(
		'Agency' => array(
			'foreignKey' => 'agencyId'
		)
    );

    public $hasOne = array(
        'ConnectCicForm' => array(
            'foreignKey' => false,
            'conditions' => array('ArchiveRequest.formId = ConnectCicForm.formId')
        ),
        'Request' => array(
            'foreignKey' => 'requestId'
        )
    );

    public $hasMany = array(
      'ArchiveResponse' => array(
          'foreignKey' => 'requestId'
      ),
      'ArchiveConnectCicRequest' => array(
          'foreignKey' => 'requestId'
      ),
      'ArchiveRequestValue' => array(
          'foreignKey' => 'requestId'
      )
    );
    
    public function beforeSave($options=array())
    {
        parent::beforeSave($options);

        // Prevent saving form notes that are larger than the requestFormNotes field will allow.
        // TODO: Deprecate the requestFormNotes.  No one likes having these printed anyway.
        if (!empty($this->data[$this->alias]['requestFormNotes']))
        {
            if (strlen($this->data[$this->alias]['requestFormNotes']) > 2048)
                $this->data[$this->alias]['requestFormNotes'] = 
                    substr($this->data[$this->alias]['requestFormNotes'], 0, 2048);
        }
    }
    
    public function getGeneralResponse($responseId)
    {
        $response = $this->ArchiveResponse->find('first', array(
            'fields' => array('responseId', 'agencyId', 'responseTime', 'requestId', 'userName', 'userLastName', 
                              'userFirstName', 'connectCicId', 'responseType', 'responseHit', 'isBroadcast',
                              'messageData', 'userFullNameLast'),
            'contain' => array(
                'ArchiveResponseValue' => array(
                    'fields' => array('responseId', 'connectCicTag', 'connectCicTagValue')
                ),
                'ArchiveImage' => array(
                    'fields' => array('fileName'),
                    'order' => 'number asc'
                )
            ),
            'conditions' => array(
                'responseId' => $responseId
            )
        ));
        $response = $response['ArchiveResponse'];

        return array(
            'ArchiveRequest' => array(
                'userName' => $response['userName'],
                'userFirstName' => $response['userFirstName'],
                'userLastName' => $response['userLastName'],
                'userFullNameLast' => $response['userFullNameLast'],
                'requestId' => 0,
                'agencyId' => $response['agencyId'],
                'requestDate' => date('Y-m-d H:i:s'),
                'agencyDeviceId' => 0,
                'formId' => '',
                'requestFormText' => '',
                'requestIsNew' => 0
            ),
            'ConnectCicForm' => array(
                'formName' => '',
                'formMessageKey' => '',
                'formTitle' => 'Unsolicited Messages',
            ),
            'ArchiveConnectCicRequest' => array(
                'requestIdentifier' => '',
                'requestDescription' => 'Unsolicited Messages',
                'requestInfo' => '',
            ),
            'ArchiveResponse' => array(
                $response
            )
        );
    }
    
    public function readFullRequest($requestId, $responseId=null)
    {
        $contain = array(
            'ConnectCicForm' => array(
                'fields' => array('formName', 'formTitle')
            ),
            'ArchiveRequestValue' => array(
                'fields' => array('requestId', 'connectCicTag', 'connectCicTagValue')
            ),
            'ArchiveConnectCicRequest' => array(
                'fields' => array('connectCicRequestId', 'requestId', 'connectCicId', 'requestIdentifier', 
                                  'requestDescription', 'requestInfo')
            ),
        );

        // Read the request information
        $request = $this->find('first', array(
            'contain' => $contain,
            'conditions' => array(
                'ArchiveRequest.requestId' => $requestId
            )
        ));
        
        // We have to break this out into a separate inquiry due to some goofy bug with CakePHP and contains.  It 
        // results in the PHP script timing out because it'll read all the ConnectCIC Request records (which is weird 
        // since that's not even related to responses)
        $options = array(
            'fields' => array('requestId'),
            'contain' => array(
                'ArchiveResponse' => array(
                    'fields' => array('responseId', 'agencyId', 'responseTime', 'requestId', 'userName', 'userLastName', 
                                      'userFirstName', 'connectCicId', 'responseType', 'responseHit', 'isBroadcast',
                                      'messageData', 'system', 'class'),
                    'order' => 'responseTime asc',

                    'ArchiveResponseValue' => array(
                        'fields' => array('responseId', 'connectCicTag', 'connectCicTagValue')
                    ),
                    'ArchiveImage' => array(
                        'fields' => array('fileName'),
                        'order' => 'number asc'
                    )
                )
            ),
            'conditions' => array(
                'ArchiveRequest.requestId' => $requestId
            )
        );
        
        if ($responseId !== null)
            $options['contain']['ArchiveResponse']['conditions']['ArchiveResponse.responseId'] = $responseId;
        
        $responses = $this->find('first', $options);

        $request['ArchiveResponse'] = $responses['ArchiveResponse'];
        return $request;        
    }
    
    public function restoreFromArchive($deviceId, $requestId, $options=array())
    {   
        // Nothing to do if the request already exists in the request table.
        if ($this->Request->hasAny(array('requestId' => $requestId)))
            return true;

        // Fail if the request doesn't exist in the archive table
        if (!$this->hasAny(array('requestId' => $requestId)))
            return false;
        
        $this->data = $this->readFullRequest($requestId);

        // Start a transaction
        $options = array_merge($options, array('atomic' => true));
        if (!$this->transaction(null, $options['atomic']))
            return false;

        // Copy all the request information
        $data = array(
            'Request' => array(
                'requestId' => $requestId,
                'agencyId' => $this->data['ArchiveRequest']['agencyId'],
                'formId' => $this->data['ArchiveRequest']['formId'],
                'formTitle' => $this->data['ArchiveRequest']['formTitle'],
                'connectCicMessageType' => $this->data['ArchiveRequest']['connectCicMessageType'],
                'requestIsNew' => false,
                'agencyDeviceId' => $deviceId,
                'userName' => $this->data['ArchiveRequest']['userName'],
                'userLastName' => $this->data['ArchiveRequest']['userLastName'],
                'userFirstName' => $this->data['ArchiveRequest']['userFirstName']
            ),
            'ConnectCicRequest' => array(),
            'RequestValue' => $this->data['ArchiveRequestValue']
        );
        
        // If we don't clear this out, we'll get a foreign key constraint violation
        if (empty($data['Request']['formId']))
            unset($data['Request']['formId']);

        foreach($this->data['ArchiveConnectCicRequest'] as $index => $connectCicRequest)
            $data['ConnectCicRequest'][$index] = array_merge($connectCicRequest, array(
                    'requestIsNew' => 0,
                    'containsHit' => 0,
                    'totalNewResponses' => 0,
                    'totalVisibleResponses' => 0
                ));

        // Save the data
        if (!$this->Request->saveAll($data, array('atomic' => false)))
            return $this->transaction(false, $options['atomic']);

        // Copy all the response information
        foreach($this->data['ArchiveResponse'] as $response)
        {
            $data = array(
                'Response' => array(
                    'responseId' => $response['responseId'],
                    'agencyId'  => $response['agencyId'],
                    'responseTime' => $response['responseTime'],
                    'requestId' => $requestId,
                    'agencyDeviceId' => $deviceId,
                    'userName' => $response['userName'],
                    'userLastName' => $response['userLastName'],
                    'userFirstName' => $response['userFirstName'],
                    'connectCicId' => $response['connectCicId'],
                    'responseType' => $response['responseType'],
                    'responseHit' => $response['responseHit'],
                    'isBroadcast' => $response['isBroadcast'],
                    'messageData' => $response['messageData'],
                    'system' => $response['system'],
                    'class' => $response['class'],
                    'responseIsNew' => false,
                )
            );
            
            if (!empty($response['ArchiveResponseValue']))
                $data['ResponseValue'] = $response['ArchiveResponseValue'];

            unsetIfEmpty($data, 'requestId');
            unsetIfEmpty($data, 'connectCicId');
            unsetIfEmpty($data, 'hitType');
            unsetIfEmpty($data, 'system');
            unsetIfEmpty($data, 'class');
            
            if (!$this->Request->Response->saveAll($data, array('atomic' => false)))
                return $this->transaction(false, $options['atomic']);
        }
                
        // Commit the transaction
        return $this->transaction(true, $options['atomic']);
    }
}
