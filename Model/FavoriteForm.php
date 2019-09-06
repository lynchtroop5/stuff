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

// A join model which creates the many-to-many relationship between User and ConnectCicForm to represent the user's
// favorite forms.
class FavoriteForm extends AppModel
{
	public $useTable     = 'tblAgencyUserFavoriteForm';
	public $primaryKey   = 'favoriteFormId';
	
	// Since this is a HABTM relationship, we don't need to define them or any data validation.  CakePHP handles it 
	// automatically for us.

    public function saveFavorite($userId, $formId)
    {
        $existing = $this->find('first', array(
            'fields' => array('favoriteFormId'),
            'conditions' => array(
                'agencyUserId' => $userId,
                'formId' => $formId
            )
        ));

        if (!empty($existing))
            return;
        
        $this->create(array(
            'agencyUserId' => $userId,
            'formId' => $formId
        ));
        
        return $this->save();
    }

    public function deleteFavorite($userId, $formId)
    {
        return $this->deleteAll(array(
            'agencyUserId' => $userId,
            'formId' => $formId
        ));
    }
}
