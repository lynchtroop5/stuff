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
App::uses('MessageQueueException', 'Error/Exception');

class MessageQueuesController extends AppController
{
    // Models used by this controller
    var $uses = array('MessageQueue', 'ArchiveResponse');

    // List of XML tags that shouldn't be stored as parsed values
    private static $ignoreTags = array(
        'CurrentDataBlock', 'Request', 'MessageData', 'Hit', 'ResponseType',
        'Image', 'DrilldownTransactions', 'IntTransId', 'MessageType', 'SecondaryRequests',
        'IntInterfaceName', 'IntSystemName', 'QueryState', 'SendToUser', 'Priority',
        'Broadcast', 'Unsolicited', 'RawMessageData', 'System', 'Record'
    );
    
    // Namespace array used to normalize XML Namespace prefixes to what we use internally.
    private static $namespaces = array(
        'api' => 'http://schema.commsys.com/xml/ConnectCic/Api',
        'clips' => 'http://schema.commsys.com/xml/Clips/Api/ConnectCic',
        'tag' => 'http://schema.commsys.com/xml/ConnectCic/Api/Tags'
    );

    // MessageQuery variable is used to get the payload response from the database
    public $messageQuery = array();

    public function process_queue()
    {
        // Disable notices that screw up our processing.
        error_reporting(0);

        // TODO: Prefill information is stored in this array.  It's nasty, but it works for now.
        //       This will need to be cleaned up somehow, later.
        $this->prefillRequests = array();

        $this->parseResponses();

        // Array of keys that need to be included in the response.
        $include = array('request', 'class', 'hit');
        $responseQuery = [];
        foreach($this->messageQuery as $response) {
            // Switch all keys to lower case to prevent possible case errors.
            $responseArr = array_change_key_case($response, CASE_LOWER);
            // returns an array that intersects with the keys from the $include array
            $responseQuery[] = array_intersect_key($responseArr, array_flip($include));
        }

        $requestSummary = $this->ArchiveResponse->ArchiveRequest->Request->getSummary(
            $this->CurrentAgency->agencyId,
            $this->CurrentDevice->agencyDeviceId);

        $summary = array(
            'requests' => $requestSummary,
            'response' => $responseQuery,
            'prefill' => $this->prefillRequests
        );
        $this->set('summary', $summary);
    }
       
    private function parseResponses() 
    {
        // Read all the available messages in queue for this device.
        $validStates = array('Response', 'Response30');
        $messages = $this->MessageQueue->find('all', array(
            'fields' => array('messageIdentity', 'message', 'processState', 'isCopied', 'sourceDeviceAlias'),
            'conditions' => array(
                'mailbox' => $this->CurrentDevice->deviceAlias,
                'processState' => $validStates
            ),
            'order' => 'createDate'
        ));
        
        // Start a transaction
        $this->MessageQueue->transaction(null, true);

        // Process all the messages. Any messages that fail will be placed into the $errors array.
        $errors = array();
        $processed = array();
        foreach($messages as $message)
        {
            $message = $message['MessageQueue'];

            try
            {
                libxml_use_internal_errors(true);
                $xml = Xml::build($message['message']);
                $namespaces = array_flip($xml->getNamespaces(true));

                // Convert the XML to an array and pass the resulting message to the correct processing function.
                $payload = Set::reverse($xml);
                // Set messageQuery to the payload array for message handling.
                $this->messageQuery[] = $payload['LawEnforcementTransaction']['Transaction']['Response'];

                if ($this->{"handle{$message['processState']}"}($message, $payload, $namespaces))
                    $processed[] = $message['messageIdentity'];
                else
                    $errors[] =  array(
                        'message' => $message,
                        'error' => 'Message id ' . $message['messageIdentity'] . ' failed to process.'
                    );
            }
            catch(Exception $e)
            {
                $errors[] = array(
                    'message' => $message,
                    'error' => 'Exception thrown while processing Message id ' . $message['messageIdentity'] . ': '
                        . $e->getMessage()
                );
            }
        }
        
        // Just send the raw LawEnforcementTransaction to the user as is with a statement indicating the error.
        if (!empty($errors))
        {
            $copy = $errors;
            foreach($copy as $index => $error) {
                try
                {
                    $this->queueErrorResponse($error['error'], $error['message']);
                    $processed[] = $error['message']['messageIdentity'];
                    unset($copy[$index]);
                }
                catch(Exception $e)
                {
                    $errors[$index]['error'] = "Failed to enqueue message that failed processing. " 
                        . "Original Error:\n\n" . $error['error'];
                }
            }
        }

        // Dequeue all processed messages.
        if (!empty($processed))
            $this->MessageQueue->deleteAll(array('messageIdentity' => $processed));
        
        // Deal with any errors that failed to get directed towards the user. All we can do is change them in the queu
        // to a new state (so they're not lost) and log them in the CLIPS error log.
        //
        // This should almost never happen.
        if (!empty($errors)) {
            $ids = Set::extract($errors, '{n}.message.messageIdentity');
            
            // Log each of the messages that failed
            foreach($errors as $id => $error) {
                CakeLog::write('error', "Message (id {$error['message']['messageIdentity']}) failed to process. " 
                    . "{$error['error']}\n{$error['message']['message']}");
            }
            
            // UpdateAll doesn't behave like other Cake model functions.  We have to quote the param ourself.
            $this->MessageQueue->updateAll(
                array('processState' => '\'CLIPS_Error\''),
                array('messageIdentity' => $ids)
            );
        }

        return $this->MessageQueue->transaction(true, true);
    }

    // This routine just saves any message from tblConnectCicMessageQueue as a general response for the current device
    // No processing on the message occurs and all information is assumed from the current user session.
    function queueErrorResponse($error, $message) {
        $generatedReturn = "CLIPS failed to process the message returned by ConnectCIC.\n\n"
                . "Reason:\n$error\n\n"
                . "Message:\n{$message['message']}";
                
        // Map the XML data to database columns and save the response data
        $data = array(
            'Response' => array(
                'agencyId'  => $this->CurrentAgency->agencyId,
                'responseTime' => date('Y-m-d H:i:s'),
                'requestId' => null,
                'agencyDeviceId' => $this->CurrentDevice->agencyDeviceId,
                'deviceName' => $this->CurrentDevice->name,
                'computerName' => $this->CurrentDevice->computerName,
                'ipAddress' => $this->request->clientIp(),
                'userName' => $this->CurrentUser->userName,
                'userLastName' => $this->CurrentUser->userLastName,
                'userFirstName' => $this->CurrentUser->userFirstName,
                'connectCicORI' => $this->CurrentDevice->ori,
                'connectCicMnemonic' => $this->CurrentDevice->mnemonic,
                'connectCicId' => null,
                'responseType' => 'CLIPS Error',
                'responseHit' => null,
                'isBroadcast' => 0,
                'messageData' => $generatedReturn,
                'responseIsNew' => true,
            )
        );
            
        if (!$this->ArchiveResponse->Response->saveResponse($data, array('atomic' => false)))
            throw new MessageQueueException('Failed to save message to database', $data);
    }
    
    private function handleResponse($message, $payload, $namespaces)
    {            
        $session = @$payload['LawEnforcementTransaction']['Session'];
        $response = @$payload['LawEnforcementTransaction']['Transaction']['Response'];

        // In most cases, we get only one response.  But, sometimes we have to deal with images too, which will be 
        // additional response nodes.
        $text = $images = null;
        $this->determineResponseNodes($response, $text, $images);

        $responseType = ((!empty($text['ResponseType']))
                ? $text['ResponseType']
                : 'Unknown');
        switch(strtolower($responseType))
        {            
        case 'status':
            return $this->processStatus($message, $payload, $session, $text);
            
        case 'connectcictransactioninformation':
            return $this->processTransactionInformation($message, $payload, $session, $text);

        default:
            // Handle all other response types and their images, if any.
            if (($responseId = $this->processResponse($message, $payload, $session, $text)))
            {
                if (!empty($images))
                    return $this->processImages($responseId, $message, $payload, $session, $images);
                
                return true;
            }

            throw new MessageQueueException('Message processing failed for an unknown reason', $message);
        }
    }

    private function handleResponse30($message, $payload, $ns)
    {        
        // Deal with annoying issue in SimpleXml where you can't get the root node namespace. We use nstag
        // first in case it ever gets fixed.
        $root = $payload[self::nstag('api', 'ConnectCicApi', $ns)];
        if (empty($root))
            $root = $payload['ConnectCicApi'];
        $transaction = $root[self::nstag('api', 'Transactions', $ns)]
                            [self::nstag('api', 'Transaction', $ns)];
        $message['debug']['payload'] = $payload;
        $message['debug']['trans'] = $transaction;
        
        if (!empty($transaction))
        {
            // Search for the first transaction node.  We scan past any attributes.
            $node = key($transaction);
            while($node != '' && $node[0] == '@' && next($transaction) !== false)
                $node = key($transaction);

            if ($node == self::nstag('clips', 'FormFill', $ns))
                return $this->processFormPrefill($message, $payload, $ns);
            
            if ($node == self::nstag('api', 'DeviceMessage', $ns))
                return $this->processDeviceMessage($message, $payload, $ns);

            if ($node == self::nstag('clips', 'ClearFormFill', $ns))
                return $this->processClearFormPrefill($message, $payload, $ns);
            
            $message['debug']['ns'] = self::$namespaces;
            $message['debug']['docns'] =  $ns;
            throw new MessageQueueException("Unknown transaction type '$node'", $message);
        }
        
        throw new MessageQueueException('Empty transaction type', $message);
    }
    
    // Maps our standard namespace prefixes to the document prefixes.  This is necessary because CakePHP serializes the
    // XML to an array using the document prefixes.  Since these may change from vendor to vendor, we have to normalize
    // them by the actual namespace URL.
    private static function nstag($stdPrefix, $tag, $docns)
    {
        // Get the namespace URL for our standard prefix.
        $ns = self::$namespaces[$stdPrefix];

        // Get the document prefix based on that URL.
        $docPrefix = $docns[$ns];
        if ($docPrefix == '')
            return $tag;

        // Return the document prefix and tag name.
        return $docPrefix . ':' . $tag;
    }
    
    private static function striptag($tag)
    {
        $pos = strpos($tag, ':');
        if ($pos !== false)
            $tag = substr($tag, $pos + 1);
        return $tag;
    }
    
    // Scans the response nodes looking for a text response and/or images.  The results are stored in $text and $images,
    // respectively.  $text should only ever be one node.  $images will always be an array.
    private function determineResponseNodes(&$response, &$text, &$images)
    {
        $images = array();
        if (!empty($response) && !is_int(key($response)))
        {
            $responseType = ((!empty($response['ResponseType']))
                    ? $text['ResponseType']
                    : 'Unknown');
            if ($responseType == 'Image')
                $images[] = $response;
            else
                $text = $response;

            return;
        }
        
        if (empty($response) || !is_array($response))
            throw new Exception('Message contains no responses');
        
        $total = count($response);
        for($a = 0; $a < $total; ++$a)
        {
            $responseType = ((!empty($response[$a]['ResponseType']))
                    ? $response[$a]['ResponseType']
                    : 'Unknown');
            if ($responseType == 'Image')
            {
                $images[] = $response[$a];
                continue;
            }
            
            if ($text) {
                throw new ClipsException('Multiple response text nodes present in return.');
            }
            
            $text = $response[$a];
        }
    }

    private function processStatus($message, $payload, $session, $text)
    {         
        // isCopied flag of 2 means the message is a copy.  The copied status messages have no use here.   
        if ($message['isCopied'] == '2')
            return true;

        // CLIPS is so awesome at processing status messages, it can do it without even doing it.  Indicate our success
        // to The Caller.
        return true;
    }
    
    private function processTransactionInformation($message, $payload, $session, $text)
    {
        // Set Request and ExternalRequest Model varaibles
        $requestModel = $this->ArchiveResponse->ArchiveRequest->Request;
        $externalRequestModel = $this->ArchiveResponse->ArchiveRequest->Request->ExternalRequest;

        // isCopied flag of 2 means the message is a copy.  The copied TransactionInformationMessages have no use 
        // here.
        if (@$message['isCopied'] == '2')
            return true;

        // If we have a request ID, see if the original request was archived. If so, restore it.
        if (!empty($text['Request']['Id']))
        {
            // Tell the ArchiveRequest table to copy its data into the Request table.  This will end up doing nothing if the
            // data is already in the Request table.
            // 
            //HACK: If the request doesn't exist, return success.  There really is no good way to recover from this
            //      other than setting each future response for this request to a general response.
            $rq = ClassRegistry::init('ArchiveRequest');
            if (!$rq->restoreFromArchive($this->CurrentDevice->agencyDeviceId, $text['Request']['Id']))
                return true;
        }

        // Sort the data detail array by importance.  The highest priority item will be at the top.
        if (!empty($text['Detail']))
        {
            $details = $text['Detail'];
            uasort($details, function($a, $b) {
                    $left = ((@$a['@importance'] == 'None') ? 2147483647 : (int)@$a['@importance']);
                    $right = ((@$b['@importance'] == 'None') ? 2147483647 : (int)@$b['@importance']);
                    if ($left < $right)      return -1;
                    else if ($left > $right) return 1;
                    return 0;
                });

            // Combine the first two highest priority detail values into a single string, space separated.
            $details = array_slice($details, 0, 2);
            $info = implode(' ', Set::classicExtract($details, '{s}.@'));
        }
        else
            $info = '';
        
        // Remove excess whitespace and truncate excess length
        $info = preg_replace('/\s\s+/', ' ', $info);
        if (strlen($info) > 128)
            $info = substr($info, 0, 128);
            
        // Set the request details.
        $requestId = @$text['Request']['Id'];
        $requestIdentifier = @$text['TransactionIdentifier'];
        $requestDescription = (!empty($text['Description']) ? $text['Description'] : 'Unknown');
        $isExternalRequest = false;

        // We use the ConnectCICRequestId (ConnectCIC's IntTransId) to determine if this is a user submitted
        // request from CLIPS, or an externally generated request that was copied to us.
        $connectCicTransactionId = @$text['IntTransId'];
        // If this is not empty, we have an externally generated request. If it is empty, we can't use this
        // request.
        if (!empty($payload['LawEnforcementTransaction']['Redirect']['IntTransId']))
        {
            // Set externally generated request details.
            $connectCicTransactionId = $payload['LawEnforcementTransaction']['Redirect']['IntTransId'];
            $isExternalRequest = true;
            $requestIdentifier = 'NA';
            $requestDescription = 'External Request';
        
            // Using the connectCicTransactionId; get the cooresponding requestId. If we didn't
            // get one, we need to generate one
            $requestId = $externalRequestModel->externalIdToClipsId($connectCicTransactionId);
            if (empty($requestId)) {
                // Reset requestModel state. 
                $requestModel->clear();
                // Save the requestModel by archiving request data, saving the request data, and 
                // committing the transaction.
                $success = $requestModel->saveRequest(array(
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
                        'formTitle' => 'External Request',
                        'connectCicMessageType' => 'External Request',
                        'requestFormText' => '',
                        'requestFormNotes' => '',
                        'requestIsNew' => true
                    )
                ));
                
                // If transaction was committed successfully then save model data
                // to the database.
                if ($success) {
                    $requestId = $requestModel->id;
                    $externalRequestModel->clear();
                    $externalRequestModel->save(array(
                        'requestId'    => $requestId,
                        'connectCicId' => $connectCicTransactionId
                    ));
                }
            }
        }

        // Set the archive table information
        $data = array(
            'requestId' => $requestId,
            'connectCicId' => $connectCicTransactionId,
            'requestIdentifier' => $requestIdentifier,
            'requestDescription' => $requestDescription,
            'requestInfo' => $info
        );

        // Save the archive table information.
        $this->ArchiveResponse->ArchiveRequest->ArchiveConnectCicRequest->clear();
        if (!$this->ArchiveResponse->ArchiveRequest->ArchiveConnectCicRequest->save($data))
            return false;

        // Save the live table information
        $data = array_merge($data, array(
            'connectCicRequestId' => $this->ArchiveResponse->ArchiveRequest->ArchiveConnectCicRequest->id,
            'requestIsNew' => 1,
            'containsHit' => 0,
            'totalNewResponses' => 0,
            'totalVisibleResponses' => 0
        ));
        $this->ArchiveResponse->ArchiveRequest->ArchiveConnectCicRequest->ConnectCicRequest->clear();
        if (!$this->ArchiveResponse->ArchiveRequest->ArchiveConnectCicRequest->ConnectCicRequest->save($data))
            return false;
        return true;
    }
    
    private function processResponse($message, $payload, $session, $text)
    {        
        // If this particular respone type doesn't exist in our lookup table yet, add it.
        $responseType = ((!empty($text['ResponseType']))
                ? $text['ResponseType']
                : 'Unknown');

        $rt = ClassRegistry::init('ResponseType');
        $rt->saveResponseType($this->CurrentAgency->agencyId, $responseType);
        
        // If this particular hit type doesn't exist in our lookup table yet, add it.
        $hitType = ((@$text['Hit']['Detected']) ? @$text['Hit']['Banner'] : null);
        if (!empty($hitType))
        {
            $ht = ClassRegistry::init('HitType');
            $ht->saveHitType($this->CurrentAgency->agencyId, $hitType);
        }

        // Set type provided by the Class object.
        $classType = (!empty($text['Class'])) ? $text['Class'] : null;
        // Set system provided by the System object.
        $systemMessage = (!empty($text['System'])) ? $text['System'] : null;

        // Alter the response banner to indicate whether the message was, or is, copied.
        $requestId = ((empty($text['Request']['Id'])) ? null : $text['Request']['Id']);

        // Set ExternalRequest Model varaible
        $externalRequestModel = $this->ArchiveResponse->ArchiveRequest->Request->ExternalRequest;

        if (empty($requestId))
        {
            // If this is not empty, we have an externally generated request. If it is empty, we can't use this
            // request.
            $connectCicTransactionId = @$payload['LawEnforcementTransaction']['Redirect']['IntTransId'];
            if (!empty($connectCicTransactionId)) {
                // Using the connectCicTransactionId; generate the cooresponding requestId.
                $requestId = $externalRequestModel->externalIdToClipsId($connectCicTransactionId);
                if ($requestId === false) 
                    $requestId = null;
            }
        }

        if (@$message['isCopied'] == '1')
            $responseType .= ' * ORIGINAL MESSAGE COPIED *';
        else if (@$message['isCopied'] == '2' && !empty($message['sourceDeviceAlias']))
        {
            // Clear the request id if this is a copy.
            $requestId = null;

            // TODO: It would be nice if we could get the Device Name here.
            $responseType .= ' * COPIED FROM * ' . strtoupper($message['sourceDeviceAlias']) . '*';
        }

        // Determine if the request ID exists as a record in the ConnectCIC Request table.  If it
        // does not, we didn't get transaction information for this request.  Force the response to
        // general responses.
        //
        // NOTE: Right now this should only happen on "Not enough information to run a transaction."
        //       When we start rolling into states that don't provide control fields, it could happen
        //       more frequently.
        if (!empty($requestId))
        {
            $count = $this->ArchiveResponse->ArchiveRequest->ArchiveConnectCicRequest->find('count', array(
                    'conditions' => array('requestId' => $requestId)
            ));
            if ($count <= 0)
                $requestId = null;
        }
        
        // Tell the ArchiveRequest table to copy its data into the Request table.  This will end up doing nothing if the
        // data is already in the Request table.  If the request doesn't exist at all, force the response to general
        // responses.
        if (!empty($requestId))
        {
            $rq = ClassRegistry::init('ArchiveRequest');
            if (!$rq->restoreFromArchive($this->CurrentDevice->agencyDeviceId, $requestId))
                $requestId = null;
        }
        
        // Sanity check to verify that the request was actually restored from archive and we have
        // everything we need before proceeding.  Ideally, this should never happen, but there have
        // been some issues reported by end users that seems to imply it does.
        if (!empty($requestId))
        {
            $condition = array('requestId' => $requestId);
            $exists = $this->ArchiveResponse->ArchiveRequest->Request->hasAny($condition)
                   && $this->ArchiveResponse->ArchiveRequest->Request->ConnectCicRequest->hasAny($condition);
            if (!$exists)
                $requestId = null;
        }
        
        if (empty($text['MessageData']))
            throw new MessageQueueException('Response contains no message.', $message);
        
        // Map the XML data to database columns and save the response data
        $data = array(
            'Response' => array(
                'agencyId'  => $this->CurrentAgency->agencyId,
                'responseTime' => date('Y-m-d H:i:s'),
                'requestId' => $requestId,
                'agencyDeviceId' => $this->CurrentDevice->agencyDeviceId,
                'deviceName' => $this->CurrentDevice->name,
                'computerName' => $this->CurrentDevice->computerName,
                'ipAddress' => $this->request->clientIp(),
                'userName' => $this->CurrentUser->userName,
                'userLastName' => $this->CurrentUser->userLastName,
                'userFirstName' => $this->CurrentUser->userFirstName,
                'connectCicORI' => $this->CurrentDevice->ori,
                'connectCicMnemonic' => $this->CurrentDevice->mnemonic,
                'connectCicId' => ((empty($text['Request']['ConnectCICRequestId'])) 
                    ? null 
                    : $text['Request']['ConnectCICRequestId']),
                'responseType' => $responseType,
                'responseHit' => $hitType,
                'isBroadcast' => ((!empty($text['Broadcast'])) ? $text['Broadcast'] : 0),
                'messageData' => $text['MessageData'],
                'responseIsNew' => true,
                'hitConfirmation' => ((!empty($text['Class'])) ? $text['Class'] : false),
                'class' => $classType,
                'system' => $systemMessage
            )
        );

        // Save parsed values
        $parsedValues = array_diff_key($text, array_flip(self::$ignoreTags));
        
        // TODO: Warrant Exchange has been postponed.  The code wasn't working when I commented this out, so it needs
        //       more thorough testing and possibly more code pulled from CLIPS 2.0.  We are now waiting for an update
        //       from the state indicating we can move forward.
        /*
        // Handle warrant exchange; This is only necessary in IA_IOWA
        if ($text['IntSystemName'] == 'WARRANT_RESULT')
        {
            $parsedValues = array_merge($parsedValues,
                $this->processWarrantExchange($text['RawMessageData']));
        }
        */
        if (!empty($parsedValues))
        {
            $data['ResponseValue'] = array();
            foreach($parsedValues as $tag => $value)
                $data['ResponseValue'][] = array(
                    'connectCicTag' => $tag,
                    'connectCicTagValue' => $value
                );
        }

        // Save drilldowns if the message isn't a copy.
        if (@$message['isCopied'] != '2' && !empty($text['DrilldownTransactions']['DrilldownTransaction']))
        {
            $data['ResponseDrilldown'] = array();
            
            // If there is only one node, it's not turned into an array by the XML parser.
            // Fix that here.
            if (!is_numeric(key($text['DrilldownTransactions']['DrilldownTransaction'])))
            {
                $temp = $text['DrilldownTransactions']['DrilldownTransaction'];
                $text['DrilldownTransactions']['DrilldownTransaction'] = array();
                $text['DrilldownTransactions']['DrilldownTransaction'][] = $temp;
            }

            foreach($text['DrilldownTransactions']['DrilldownTransaction'] as $drilldown)
            {
                if (empty($drilldown['DrilldownId']) || empty($drilldown['Title']))
                    continue;
                
                if (empty($drilldown['Column']) || empty($drilldown['Row']))
                    continue;
                
                if (empty($drilldown['Length']))
                    continue;
                                
                $data['ResponseDrilldown'][] = array(
                    'connectCicId' => $drilldown['DrilldownId'],
                    'title' => $drilldown['Title'],
                    'isCommon' => ((empty(@$drilldown['Common'])) ? '0' : '1'),
                    'length' => $drilldown['Length'],
                    'row' => $drilldown['Row'],
                    'column' => $drilldown['Column']
                );
            }
        }
            
        if (!$this->ArchiveResponse->Response->saveResponse($data, array('atomic' => false)))
            throw new MessageQueueException('Failed to save message to database', $message);

        return $this->ArchiveResponse->id;
    }
    
    private function processImages($responseId, $message, $payload, $session, $images)
    {
        $this->ArchiveResponse->ArchiveImage->clear();

        // Save images
        $data = array();
        foreach($images as $index => $image)
        {            
            $requestId = @$image['Request']['Id'];
            $id = md5($requestId . ':' . $responseId . ':' . ($index + 1));
            $fileName = $id . '.jpg';
            $filePath = WWW_ROOT . DS . 'img' . DS . 'return' . DS . $fileName;

            // If there are attributes on ImageData element, which there usually are, Set::reverse() stored the
            // actual value of ImageData in the '@' array key index.
            $imageData = @$image['Image']['ImageData']['@'];
            if (empty($imageData)) {
                $imageData = @$image['Image']['ImageData'];
            }
            
            if (empty($imageData)) {
                throw new MessageQueueException('An image was detected, but no image data found.', $message);
            }
            
            $imageData = base64_decode($imageData);
            file_put_contents($filePath, $imageData);

            $data[] = array(
                    'responseId' => $responseId, 
                    'number' => $index + 1,
                    'upperTop' => @$image['Image']['UpperTopText'],
                    'upperBottom' => @$image['Image']['UpperBottomText'],
                    'lowerTop' => @$image['Image']['LowerTopText'],
                    'filename' => $fileName
            );
        }

        if (!$this->ArchiveResponse->ArchiveImage->saveAll($data, array('atomic' => false)))
            throw new MessageQueueException('Failed to save image to database', $data);
        
        return true;
    }
    
    private function processFormPrefill($message, $payload, $ns)
    {
        $prefill = ClassRegistry::init('Prefill');

        // Deal with annoying issue in SimpleXml where you can't get the root node namespace. We use nstag
        // first in case it ever gets fixed.
        $root = $payload[self::nstag('api', 'ConnectCicApi', $ns)];
        if (empty($root))
            $root = $payload['ConnectCicApi'];
        $transaction = $root[self::nstag('api', 'Transactions', $ns)]
                            [self::nstag('api', 'Transaction', $ns)];
        $formFill = $transaction[self::nstag('clips', 'FormFill', $ns)];

        // Build the initial data array.
        $banner = $formFill[self::nstag('clips', 'Banner', $ns)];
        $data = array(
            'Prefill' => array(
                'agencyId' => $this->CurrentAgency->agencyId,
                'deviceAlias' => $this->CurrentDevice->deviceAlias,
                'banner' => $banner
            ),
            'PrefillValue' => array()
        );

        // Store all the category and tag values.
        foreach($formFill[self::nstag('clips', 'Parameters', $ns)] as $category => $fields)
        {
            $category = self::striptag($category);

            // If the category has no fields, skip it.
            if (!is_array($fields))
                continue;

            foreach($fields as $tag => $values)
            {
                $tag = self::striptag($tag);

                // If values is an array, then that means there are multiple tags of the same name.  We just convert
                // everything to an array to make the next bit of processing easier.
                if (!is_array($values))
                    $values = array($values);
                
                foreach($values as $index => $value)
                {
                    if (empty($value))
                        continue;

                    $data['PrefillValue'][] = array(
                        'category' => $category,
                        'tag' => $tag,
                        'index' => $index,
                        'value' => $value
                    );
                }
            }
        }

        // Add the Prefill Transaction ID as a prefill value.  This results in the hidden Cic3TransactionId input
        // being set when the prefill is loaded.
        if (!empty($transaction['@id']))
        {
            $data['PrefillValue'][] = array(
                'category' => 'Internal',
                'tag' => 'Cic3TransactionId',
                'index' => 0,
                'value' => $transaction['@id']
            );
        }

        $prefill->clear();
        if (!$prefill->saveAll($data, array('atomic' => false)))
            throw new MessageQueueException('Failed to save prefill to database', $data);

        $nsTag = self::nstag('clips', 'FormIdentifier', $ns);
        $this->prefillRequests[] = array(
            'prefillId' => $prefill->id,
            'formId' => ((!empty($formFill[$nsTag])) ? sprintf('%s_%s', $this->CurrentAgency->interface, $formFill[$nsTag]) : null),
            'banner' => $banner
        );

        return true;
    }
    
    private function processDeviceMessage($message, $payload, $ns)
    {
        $responseType = 'Terminal to Terminal';
        $rt = ClassRegistry::init('ResponseType');
        $rt->saveResponseType($this->CurrentAgency->agencyId, $responseType);

        // Deal with annoying issue in SimpleXml where you can't get the root node namespace. We use nstag
        // first in case it ever gets fixed.
        $root = $payload[self::nstag('api', 'ConnectCicApi', $ns)];
        if (empty($root))
            $root = $payload['ConnectCicApi'];
                
        // Build the message data
        $source = $root[self::nstag('api', 'Route', $ns)]
                       [self::nstag('api', 'Source', $ns)];

        $destination = $root[self::nstag('api', 'Route', $ns)]
                            [self::nstag('api', 'Destinations', $ns)]
                            [self::nstag('api', 'Destination', $ns)];
        $destination = implode(',', array_map(function($i) { return self::striptag($i); }, $destination));

        $messageData = $root[self::nstag('api', 'Transactions', $ns)]
                            [self::nstag('api', 'Transaction', $ns)]
                            [self::nstag('api', 'DeviceMessage', $ns)]
                            [self::nstag('api', 'Content', $ns)];
		$messageData = sprintf("T2T.%s.%s\n", $source, $destination) .
			wordwrap($messageData, 80, "\n", TRUE);

        // Save the data
        $data = array(
            'Response' => array(
                'agencyId'  => $this->CurrentAgency->agencyId,
                'responseTime' => date('Y-m-d H:i:s'),
                'agencyDeviceId' => $this->CurrentDevice->agencyDeviceId,
                'deviceName' => $this->CurrentDevice->name,
                'computerName' => $this->CurrentDevice->computerName,
                'ipAddress' => $this->request->clientIp(),
                'userName' => $this->CurrentUser->userName,
                'userLastName' => $this->CurrentUser->userLastName,
                'userFirstName' => $this->CurrentUser->userFirst,
                'connectCicORI' => $this->CurrentDevice->ori,
                'connectCicMnemonic' => $this->CurrentDevice->mnemonic,
                'responseType' => $responseType,
                'isBroadcast' => 0,
                'messageData' => $messageData,
                'responseIsNew' => 1
            )
        );
        $result = $this->ArchiveResponse->Response->saveResponse($data, array('atomic' => false));
        if (!$result)
            throw new MessageQueueException('Failed to save device message to database', $data);
        return true;
    }
    
    private function processClearFormPrefill($message, $payload, $ns)
    {
        // Retrieve the ClearForMFill criteria
        $prefill = ClassRegistry::init('Prefill');
        $root = $payload[self::nstag('api', 'ConnectCicApi', $ns)];
        if (empty($root))
            $root = $payload['ConnectCicApi'];
        $clearFill = $root[self::nstag('api', 'Transactions', $ns)]
                          [self::nstag('api', 'Transaction', $ns)]
                          [self::nstag('clips', 'ClearFormFill', $ns)];

        //if (empty($clearFill))
        //    $clearFill = array();

        // store the values from the ClearFormFill message into my variables
        if (!empty($clearFill[self::nstag('clips', 'Banner', $ns)]))
            $banner = $clearFill[self::nstag('clips', 'Banner', $ns)];
        
        // delete all prefills for a device within an agency
        if (empty($banner))
        {
            return $prefill->deleteAll(array(
                'Prefill.agencyId' => $this->CurrentAgency->agencyId,
                'Prefill.deviceAlias' => $this->CurrentDevice->deviceAlias
            ));
        }

        // Delete a specific prefill by banner
        return $prefill->deleteAll(array(
                'Prefill.agencyId' => $this->CurrentAgency->agencyId,
                'Prefill.deviceAlias' => $this->CurrentDevice->deviceAlias,
                'Prefill.banner' => $banner
            ));
    }
 
    // TODO: See comment above (in processResponse) about postponing Warrant Exchange.  I'm leaving the code in
    //       so I don't have to hunt the revision I removed it from later.
    /*
    private function processWarrantExchange($rawxml)
    {
        try
        {
            libxml_use_internal_errors(true);
            $xml = Xml::build($rawxml);
            $payload = Set::reverse($xml);
            
            $linkNode = $payload['OFML']['RSP']['LINK'];
            pr($linkNode);
            exit;
            
            return array();
        }
        catch(Exception $ex)
        {
            CakeLog::write('error', 'Failed to parse warrant exchange xml: ' . $ex->getMessage() . "\n" . $rawxml);
            return array();
        }
    }
    */
}
