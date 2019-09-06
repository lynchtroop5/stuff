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

// Represents a recently used password for a given user.
class PasswordHistory extends AppModel
{
	public $useTable     = 'tblPasswordHistory';
	public $primaryKey   = 'userPasswordId';

	public $belongsTo = array(
		'User' => array(
			'foreignKey' => 'agencyUserId'
		)
	);

	public $validate = array(
		'agencyUserId' => array(
			'rule' => array('rowExists', 'User.agencyUserId'),
			'message' => 'Agency user ID must be the ID of an existing user.'
		),
		'userPassword' => array(
			'notBlank' => array(
				'rule' => 'notBlank',
				'message' => 'User password is a required field.'
			),
			'hash' => array(
				'rule' => '/[a-z0-9]{32}/i',
				'message' => 'The password must be a 32 character MD5 hash.'
			),
			'unique' => array(
				'rule' => array('unique', 'agencyUserId'),
				'message' => 'This password hash has already been saved.'
			)
		)
	);

	public function beforeSave($options=array())
	{
		$this->data['PasswordHistory']['setDate'] = date('Y-m-d H:i:s');
		return true;
	}
}
