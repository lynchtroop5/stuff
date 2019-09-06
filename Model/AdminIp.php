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

class AdminIp extends AppModel
{
	public $useTable     = 'tblAdminIp';
	public $primaryKey   = 'agencyAdminIpId';

	public $validate = array(
		'ipAddress' => array(
			'value' => array(
				'rule' => 'notBlank',
				'message' => 'IP Address is a required value.'
			),
			'ip' => array(
				'rule' => 'ip',
				'message' => 'IP Address must be a valid IP address.'
			),
			'unique' => array(
				'rule' => array('unique', 'ipAddress'),
				'message' => 'This IP address is already configured.'
			)
		)
	);
}
