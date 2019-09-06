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

// Represents a Prefill request recieved from ConnectCIC.
class Prefill extends AppModel
{
	public $useTable     = 'tblPrefill';
	public $primaryKey   = 'prefillId';
	public $displayField = 'banner';

	public $belongsTo = array(
		'Agency' => array(
			'foreignKey' => 'agencyId'
		)
	);

	public $hasMany = array(
		'PrefillValue' => array(
			'foreignKey' => 'prefillId',
			'dependent'  => true,
			'exclusive'  => true
		)
	);
	
	public $validate = array(
		'agencyId' => array(
			'rule' => array('rowExists', 'Agency.agencyId'),
			'message' => 'Agency ID must be the ID of an existing agency.'
		),
		'deviceAlias' => array(
			'rule' => array('rowExists', 'Device.deviceAlias'),
			'message' => 'Device alias must be the device alias of an existing device.'
		),
		'banner' => array(
			'rule' => 'notBlank',
			'message' => array('Banner is a required field.')
		)
	);
}
