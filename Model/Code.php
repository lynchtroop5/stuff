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

// Represents an individual code value used in a CLIPS form drop down.  This is a lookup model, so saving/deleting is 
// disabled.
class Code extends AppModel
{
	public $useTable     = 'tblCode';
	public $primaryKey   = 'codeId';

	public $belongsTo = array(
		'CodeType' => array(
			'foreignKey' => 'codeTypeId'
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
