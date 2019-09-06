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

// Represents a value contained within a given Prefill request received from ConnectCIC.
class PrefillValue extends AppModel
{
	public $useTable     = 'tblPrefillValue';
	public $primaryKey   = 'prefillValueId';

	public $belongsTo = array(
		'Prefill' => array(
			'foreignKey' => 'prefillId'
		)
	);

    public $hasOne = array(
        'PrefillMap' => array(
            'foreignKey' => false,
            'conditions' => array(
                '(PrefillMap.category = PrefillValue.category or case when PrefillMap.category IN(\'MissingPerson\',\'WantedPerson\',\'SexualOffender\') then \'Person\' else PrefillMap.category end = PrefillValue.category) and PrefillMap.tag = PrefillValue.tag and PrefillMap.index = PrefillValue.index'
            )
        )
    );
    
	public $validate = array(
		'prefillId' => array(
			'rule' => array('rowExists', 'Prefill.prefillId'),
			'message' => array('Prefill ID must be the id of an existing prefill.')
		)
	);
}
