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

// The joining model creating a many-to-many relationship between FormGroup and ConnectCicForm
class FormGroupForm extends AppModel
{
	public $useTable     = 'tblAgencyFormGroupForm';
	public $primaryKey   = 'agencyFormGroupFormId';
	
	// As a joining table, we don't really need any validation or relationships.  CakePHP handles that for us.

	public function copyForms($destGroupId, $sourceGroupId)
	{
		if (!is_numeric($destGroupId) || !is_numeric($sourceGroupId)) {
			return false;
		}

		$ds = $this->getDataSource();
		$sql = 'insert into tblAgencyFormGroupForm (agencyFormGroupId, formId) ' .
			   "select $destGroupId, formId " .
			   'from tblAgencyFormGroupForm ' .
			   "where agencyFormGroupId = $sourceGroupId";

		return $ds->execute($sql);
	}
}
