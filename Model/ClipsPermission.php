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

// Represents an individual permission in the tblPermission lookup table.  This is a lookup model, so saving/deleting is
// disabled.
class ClipsPermission extends AppModel
{
	public $useTable     = 'tblPermission';
	public $primaryKey   = 'permissionId';
	public $displayField = 'shortDescription';

	// Permission belongs to many Groups and Users, but we never need to look that information up from here.  So, we
	// don't bother adding the relationships.

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
    
    public function findAdminPermissions($fields=array())
    {
        return $this->find('all', array(
            'fields' => $fields,
            'conditions' => array(
                'isAdministratorPermission' => 1
            )
        ));
    }
}
