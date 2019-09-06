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

// Represents the archived version of a Response.
class ArchiveResponse extends AppModel
{
	public $useTable     = 'tblArchiveResponse';
	public $primaryKey   = 'responseId';
    public $virtualFields = array(
        'userFullNameLast' => 'ArchiveResponse.userLastName + \', \' + ArchiveResponse.userFirstName'
    );

    public $belongsTo = array(
		'Agency' => array(
			'foreignKey' => 'agencyId'
		),
        'ArchiveRequest' => array(
            'foreignKey' => 'requestId'
        )
    );
    
    public $hasOne = array(
        'Response' => array(
            'foreignKey' => 'responseId'
        )        
    );
    
    public $hasMany = array(
        'ArchiveResponseValue' => array(
            'foreignKey' => 'responseId'
        ),
        'ArchiveImage' => array(
            'foreignKey' => 'responseId'
        )
    );
}
