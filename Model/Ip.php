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

// Represents an IP/MASK pair for a given agency which controls user access to that agency.
class Ip extends AppModel
{
	public $useTable     = 'tblAgencyIp';
	public $primaryKey   = 'agencyIpId';
    public $virtualFields = array(
        'ipAndMask' => 'ipAddress + \'/\' + ipMask'
    );
    
	public $belongsTo = array(
		'Agency' => array(
			'foreignKey' => 'agencyId'
		)
	);

	public $validate = array(
		'agencyId' => array(
			'rule' => array('rowExists', 'Agency.agencyId'),
			'message' => 'Agency ID must be the ID of an existing agency.',
			'allowEmpty' => true
		),
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
				'rule' => array('unique', 'agencyId', 'ipAddress'),
				'message' => 'This IP address is already configured.'
			)
		),
		'ipMask' => array(
			'value' => array(
				'rule' => 'notBlank',
				'message' => 'IP Mask is a required value.'
			),
			'ip' => array(
				'rule' => 'ip',
				'message' => 'IP Address must be a valid IP address.'
			)
		)
	);
}
	