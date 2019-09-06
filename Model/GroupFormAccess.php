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

// Represents the association between an agency's permission Group and its assigned Forms.  Access can be provided by 
// either associating the form directly to the permission Group, or by associating an agency's FormGroup.
class GroupFormAccess extends AppModel
{
	public $useTable     = 'tblAgencyGroupFormAccess';
	public $primaryKey   = 'agencyGroupFormAccessId';

	// As this is a join-model for the many-to-many relationship betweeen Group and FormGroup or Form, we don't need to
	// define any relationships or validation.  CakePHP handles that for us.

	public function beforeSave($options=array())
	{
		// If formId is empty, unset it so that it is stored as NULL.
		if (empty($this->data['formId']))
			unset($this->data['formId']);
		
		// If agencyFormGroupId is empty, unset it so that it is stored as NULL.
		if (empty($this->data['agencyFormGroupId']))
			unset($this->data['agencyFormGroupId']);
        
        return true;
	}
}