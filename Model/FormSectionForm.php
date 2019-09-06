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

// A join model providing the many-to-many relationship between form sections and forms.
class FormSectionForm extends AppModel
{
	public $useTable     = 'tblFormSectionForm';
	public $primaryKey   = 'formSectionFormId';

	// Because this is a many-to-many relationship, CakePHP handles the relationships for us.  So we can skip defining
	// those.

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
