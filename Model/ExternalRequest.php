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

 // Represents the archived version of an External Request.
class ExternalRequest extends AppModel
{
	public $useTable     = 'tblExternalRequest';
	public $primaryKey   = 'externalRequestId';

	public $belongsTo = array(
		'Request' => array(
			'foreignKey' => 'requestId'
		)
    );

	// Using the connectCicTransactionId; generate and return the cooresponding requestId.
	public function externalIdToClipsId($externalRequestId)
	{
		return $this->field('requestId', array('connectCicId' => $externalRequestId));
	}
}
