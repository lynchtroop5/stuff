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

// Represents a single message sent to the state by ConnectCIC.  Multiple messages may be sent to the state from a 
// single transaction or form request.
class ConnectCicRequest extends AppModel
{
	public $useTable     = 'tblConnectCicRequest';
	public $primaryKey   = 'connectCicRequestId';

    public $belongsTo = array(
        'Request' => array(
            'foreignKey' => 'requestId'
        )
    );
}
