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

// Represents the defintion of an individual CLIPS Form.  This is a lookup model, so saving/deleting is disabled.
class ConnectCicFormStage extends AppModel
{
	public $useTable     = 'tblConnectCicFormStage';
	public $primaryKey   = 'id';

	public $belongsTo = array(
		'ConnectCicForm' => array(
			'foreignKey' => 'formId'
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
