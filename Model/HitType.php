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

// Represents a hit type as specified by ConnectCIC in the XML return.
class HitType extends AppModel
{
	public $useTable     = 'tblLookupHitType';
	public $primaryKey   = 'hitTypeId';

	public $belongsTo = array(
		'Agency' => array(
			'foreignKey' => 'agencyId'
		)
	);

	public $validate = array(
		'agencyId' => array(
			'rule' => array('rowExists', 'Agency.agencyId'),
			'message' => 'Agency ID must be the ID of an existing agency.'
		),
		'hitType' => array(
			'notBlank' => array(
				'rule' => 'notBlank',
				'message' => 'Hit type is a required field.'
			),
			'unique' => array(
				'rule' => array('unique', 'agencyId'),
				'message' => 'This hit type is already defined.'
			),
		)
	);

    public function saveHitType($agencyId, $type)
    {
        $data = array('agencyId' => $agencyId, 'hitType' => $type);
        if (!$this->hasAny($data))
            return $this->save($data);

        return true;
    }
}
