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

// Represents a form field code table used to display drop downs on CLIPS forms.  No validation is on this table as
// they're lookup tables.  This is a lookup model, so saving/deleting is disabled.
class CodeType extends AppModel
{
	public $useTable     = 'tblCodeType';
	public $primaryKey   = 'codeTypeId';

	public $hasMany = array(
		'Code' => array(
			'foreignKey' => 'codeTypeId',
			'dependent'  => true,
			'exclusive'  => true
		)
	);

	public function beforeSave($options = array())
	{
		// Prevent saving
		return false;
	}
	
	public function beforeDelete($cascade = true) 
	{
		// Prevent deleting
		return false;
	}
}
