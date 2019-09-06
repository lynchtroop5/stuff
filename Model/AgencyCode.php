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

// Represents an individual code value for an agency's code table.
class AgencyCode extends AppModel
{
	public $useTable     = 'tblAgencyCode';
	public $primaryKey   = 'agencyCodeId';

	public $belongsTo = array(
		'AgencyCodeType' => array(
			'foreignKey' => 'agencyCodeTypeId'
		)
	);

	public $validate = array(
		// agencyCodeTypeId is handled by the database via a FK constraint.  If this fails, it's a
		// developer error, not a user error.

		'code' => array(
			'rule' => 'notBlank',
			'message' => 'Code must be supplied.'
		),
		'description' => array(
			'rule' => 'notBlank',
			'message' => 'Description must be supplied.'
		)
	);
}
