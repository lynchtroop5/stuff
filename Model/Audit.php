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

// Represents a security audit event within CLIPS.
//
// TODO: Filter on agencyId, deviceName, created, userName, type, description using index
//       IX_tblAgencyAudit_agencyId_deviceName_created_userName.
class Audit extends AppModel
{
	public $useTable     = 'tblAgencyAudit';
	public $primaryKey   = 'auditId';
    
	public $virtualFields = array(
        'userFullNameLast' => 'Audit.userLastName + \', \' + Audit.userFirstName',
		'userFullName' => 'Audit.userFirstName + \' \' + Audit.userLastName'
	);

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
		'ipAddress' => array(
			'rule' => array('ip', 'IPv4'),
			'message' => 'IP Address must be a valid IPv4 address.'
		),
		'deviceName' => array(
			'rule' => 'validateDeviceName',
			'allowEmpty' => true
		),
		'deviceAlias' => array(
			'rule' => 'validateDeviceAlias',
			'allowEmpty' => true
		),
		'userName' => array(
			'rule' => 'notBlank',
			'message' => 'User Name must be specified'
		),
		'userAlias' => array(
			'rule' => 'validateUserAlias',
			'allowEmpty' => true
		),
		'userClass' => array(
			'rule' => array('inList', array('User','Agency Administrator','System Administrator','Unknown')),
			'message' => 'User Class must be \'User\', \'Administrator\', or \'PowerUser\'.'
		),
		'type' => array(
			'rule' => 'notBlank',
			'message' => 'Type must be specified'
		),
		'description' => array(
			'rule' => 'notBlank',
			'message' => 'Description must be specified'
		)
	);
	
    public function eventTypes()
    {
        return array(
            'Access Denied',
            'Account Disabled',
            'Account Forcefully Disabled',
            'Agency Configuration Change',
            'Failed Login',
            'Forced Logout',
            'Form Group Changed',
            'Lost Session',
            'Password Change',
            'Password Expired',
            'Permission Group Changed',
            'Response History Search',
            'User Login',
            'Criminal History Search',
            'Global Configuration Change',
            'ORI Added',
            'ORI Changed',
            'Request History Search',
            'User Permissions Changed'
        );
    }
    
	public function beforeValidate($options=array())
	{
		// Auto-populate the deviceAlias and userAlias
		if (!empty($this->data['Audit']['agencyId']))
		{
			if (!empty($this->data['Audit']['deviceName']) && empty($this->data['Audit']['deviceAlias']))
				$this->data['Audit']['deviceAlias'] = $this->data['Audit']['agencyId'] . ':' .
													  $this->data['Audit']['deviceName'];

			if (!empty($this->data['Audit']['userName']) && empty($this->data['Audit']['userAlias']))
				$this->data['Audit']['userAlias'] = $this->data['Audit']['agencyId'] . ':' .
												    $this->data['Audit']['userName'];
		}

		return true;
	}

	public function validateDeviceName($check)
	{
		if (Validation::blank($this->data[$this->name]['agencyId']))
			return 'Device Name may not be specified without an Agency Id.';

		if (Validation::blank($this->data[$this->name]['deviceAlias']))
			return 'Device Name may not be specified without a Device Alias.';

		return true;
	}

	public function validateDeviceAlias($check)
	{
		if (Validation::blank($this->data[$this->name]['agencyId']))
			return 'Device Alias may not be specified without an Agency Id.';

		if (Validation::blank($this->data[$this->name]['deviceName']))
			return 'Device Alias may not be specified without a Device Name.';

		$value = current($check);
		$expected = $this->data[$this->name]['agencyId'] . ':' . $this->data[$this->name]['deviceName'];
		if (!Validation::equalTo($value, $expected))
			return "Device Alias expected to be '$expected'.";

		return true;
	}

	public function validateUserAlias($check)
	{
		if (Validation::blank($this->data[$this->name]['agencyId']))
			return 'User Alias may not be specified without an Agnecy Id.';

		$value = current($check);
		$expected = $this->data[$this->name]['agencyId'] . ':' . $this->data[$this->name]['userName'];
		if (!Validation::equalTo($value, $expected))
			return "User Alias expected to be '$expected'.";

		return true;
	}
}
