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

// Represents a security group used to control a user's access to individual forms.
class FormGroup extends AppModel
{
	public $useTable     = 'tblAgencyFormGroup';
	public $primaryKey   = 'agencyFormGroupId';
	public $displayField = 'formGroupName';

	public $belongsTo = array(
		'Agency' => array(
			'foreignKey' => 'agencyId'
		)
	);

	public $hasAndBelongsToMany = array(
		'ConnectCicForm' => array(
			'with' => 'FormGroupForm',
			'foreignKey' => 'agencyFormGroupId',
			'associationForeignKey' => 'formId'
		),
		'Group'  => array(
			'with' => 'GroupFormAccess',
			'foreignKey' => 'agencyFormGroupId',
			'associationForeignKey' => 'agencyGroupId'
		),
	);
	
	public $validate = array(
		'agencyId' => array(
			'rule' => array('rowExists', 'Agency.agencyId'),
			'message' => 'Agency ID must be the ID of an existing agency.'
		),
		'formGroupName' => array(
            'on' => 'create',
			'rule' => array('unique', 'agencyId'),
			'message' => 'A form group with this name already exists.'
		)
	);
}
