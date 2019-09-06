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

// Represents a queue of messages used by CLIPS and CLIPS Server for inter-process communication.
class MessageQueue extends AppModel
{
	public $useTable     = 'tblConnectCicMessageQueue';
	public $primaryKey   = 'messageIdentity';

	// Although a Device technically has a message queue, the device model is never really stored in the session
	// or in a way it can be used to look up messages.  So, we don't bother setting up the relationships here.
	
	public $validate = array(
		'mailbox' => array(
			'notBlank' => array(
				'rule' => 'notBlank',
				'message' => 'Mailbox is a required field.'
			)/*,
			'exists' => array(
				'rule' => array('rowExists', 'Device.deviceAlias'),
				'message' => 'Mailbox must be the device alias of an existing device.'
			)*/
		),
		'message' => array(
			'rule' => 'notBlank',
			'message' => 'Message is a required field.'
		),
		'processState' => array(
			'notBlank' => array(
				'rule' => 'notBlank',
				'message' => 'Process state is a required field.'
			),
			'valid' => array(
				'rule' => array('inList', array('ForceResponse', 'ForceResponse30', 'Response', 'Response30', 'Queued')),
				'message' => 'Process state must be one of ForceResponse, Response, Response30, or Queued.'
			)
		),
		'isCopied' => array(
			'rule' => 'validateIsCopied',
			'allowEmpty' => true
		),
		'sourceDeviceAlias' => array(
			'rule' => 'validateSourceDeviceAlias',
			'allowEmpty' => true
		)
	);
	
	public function beforeValidate($options=array())
	{
		if (empty($this->data[$this->alias]['isCopied']))
			unset($this->data[$this->alias]['isCopied']);
		
		if (empty($this->data[$this->alias]['sourceDeviceAlias']))
			unset($this->data[$this->alias]['sourceDeviceAlias']);
	}

	public function beforeSave($options=array())
	{
		$this->data[$this->alias]['createDate'] = date('Y-m-d H:i:s');
		return true;
	}
    
	public function validateIsCopied($check)
	{
		if (!isset($this->data[$this->alias]['isCopied']))
			return true;

		switch($this->data[$this->alias]['isCopied'])
		{
		case 1:
		case 2:
			return true;
		}
		
		return 'Is copied must be either empty, \'1\', or \'2\'.';
	}
	
	public function validateSourceDeviceAlias($check)
	{
		// Device alias must be set if isCopied is 2, but empty otherwise.
		if (!empty($this->data[$this->alias]['sourceDeviceAlias']))
		{
			if ($this->data[$this->alias]['isCopied'] == 1)
				return 'Source device alias must be blank if is copied is set to \'1\'.';

			// Device alias must exist.
			return $this->rowExists($check, 'Device.deviceAlias');
		}
		else if ($this->data[$this->alias]['isCopied'] == 2)
			return 'Source device alias is required if is copied is set to \'2\'.';

		return true;
	 }
}
