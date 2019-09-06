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

// The join model for a many-to-many relationship between Group and Permission.
class GroupPermission extends AppModel
{
	public $useTable     = 'tblAgencyGroupPermission';
	public $primaryKey   = 'agencyGroupPermissionId';
	
	// As this is a join-model for the many-to-many relationship betweeen Group and Permission, we don't need to define
	// any relationships or validation.  CakePHP handles that for us.
}
