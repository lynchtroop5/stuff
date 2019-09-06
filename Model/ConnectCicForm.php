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

// Represents an individual CLIPS Form.  This is a lookup model, so saving/deleting is disabled.
class ConnectCicForm extends AppModel
{
	public $useTable     = 'tblConnectCicForm';
	public $primaryKey   = 'formId';
	public $displayField = 'formInterfaceTitle';

    public $virtualFields = array(
		'formInterfaceTitle' => 'ConnectCicForm.formName + \' \' + ConnectCicForm.formTitle'
	);
   
	public $hasMany = array(
		'ConnectCicFormStage' => array(
			'foreignKey' => 'formId',
			'dependent'  => true,
			'exclusive'  => true
		)
	);

    public function __construct($id=false, $table=null, $ds=null)
    {
        parent::__construct($id, $table, $ds);
        
        if (CurrentUser::loggedIn())
        {
            $this->virtualFields['isFavoriteForm'] = 
                '(select case when exists(select * ' .
                                         'from tblAgencyUserFavoriteForm ' .
                                         'where agencyUserId = ' . CurrentUser::user('agencyUserId') . ' and ' .
                                             'formId = ConnectCicForm.formId) then 1 else 0 end)';
        }
    }
    
	// ConnectCicForm belongs to many FormGroups.  It could also belong to a permission Group.  However, we have no need
	// to find out which FormGroups a form belongs to.  So, we don't set that relationship up here.  If we ever get to 
	// the point where CLIPS need to create/delete forms, then it may make sense to add the relationship here.

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
