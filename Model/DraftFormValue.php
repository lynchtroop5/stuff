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

// Represents the field values stored with a user's form drafts.
class DraftFormValue extends AppModel
{
	public $useTable     = 'tblAgencyUserDraftFormValue';
	public $primaryKey   = 'draftFormValueId';

	public $belongsTo = array(
		'DraftForm' => array(
			'foreignKey' => 'draftFormId'
		)
	);

	public $validate = array(
		'connectCicTag' => array(
			'notBlank' => array(
				'rule' => 'notBlank',
				'message' => 'ConnectCic Tag is a required field.'
			),
			'unique' => array(
				'rule' => array('unique', 'draftFormId'),
				'message' => 'This tag is already assigned a value in this draft.'
			)
		)

		// TODO: Define a requirement such that the field exists in the form.
	);
}
