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

// Represents a criminal history session initiated by a user.
class CriminalHistory extends AppModel
{
	public $useTable     = 'tblCriminalHistory';
	public $primaryKey   = 'criminalHistoryId';
    public $virtualFields = array(
        'userFullNameLast' => 'CriminalHistory.userLastName + \', \' + CriminalHistory.userFirstName',
		'userFullName' => 'CriminalHistory.userFirstName + \' \' + CriminalHistory.userLastName'
    );
    
    public $hasOne = array(
        'CriminalHistoryCustom' => array(
            'foreignKey' => 'criminalHistoryId',
            'dependent' => true
        )
    );
    
    public $hasAndBelongsToMany = array(
        'ArchiveRequest' => array(
            'with' => 'CriminalHistoryRequest',
            'foreignKey' => 'criminalHistoryId',
            'associationForeignKey' => 'requestId',
            'unique' => false // Don't delete existing records
        )
    );

    public $validate = array(
		'disposition' => array(
			'value' => array(
				'rule' => 'notBlank',
				'message' => 'Disposition is a required field.'
			)
		)
    );
}
