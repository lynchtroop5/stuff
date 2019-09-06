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

// Represents an agency's custom drop down tables.
class AgencyCodeType extends AppModel
{
	public $useTable     = 'tblAgencyCodeType';
	public $primaryKey   = 'agencyCodeTypeId';
	public $displayField = 'name';

	public $belongsTo = array(
		'Agency' => array(
			'foreignKey' => 'agencyId'
		)
	);

	public $hasMany = array(
		'AgencyCode' => array(
			'foreignKey' => 'agencyCodeTypeId',
			'dependent'  => true,
			'exclusive'  => true
		)
	);

	public $validate = array(
		'agencyId' => array(
			'rule' => array('rowExists', 'Agency.agencyId'),
			'message' => 'Agency ID must be the ID of an existing agency.'
		),
		'name' => array(
			'rule' => array('unique', 'agencyId'),
			'message' => 'This code type is already in use.'
		)
	);
}
