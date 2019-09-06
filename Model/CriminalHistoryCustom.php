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
class CriminalHistoryCustom extends AppModel
{
	public $useTable     = 'tblCriminalHistoryCustom';
	public $primaryKey   = 'criminalHistoryId';

    public $belongsTo = array(
        'CriminalHistory' => array(
            'foreignKey' => 'criminalHistoryId'
        )
    );
}
