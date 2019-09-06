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

// Represents a logical group of forms for a given interface within CLIPS.  This is used to organize related forms
// into groups.  This is a lookup model, so saving/deleting is disabled.
class FormSection extends AppModel
{
	public $useTable     = 'tblFormSection';
	public $primaryKey   = 'formSectionId';
    public $displayField = 'sectionName';
    
	public $hasAndBelongsToMany = array(
		'Form' => array(
			'className' => 'ConnectCicForm',
			'with' => 'FormSectionForm',
			'foreignKey' => 'formSectionId',
			'associationForeignKey' => 'formId'
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
