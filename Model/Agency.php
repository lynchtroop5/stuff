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

// Represents an individual agency within CLIPS.
class Agency extends AppModel
{
	public $useTable     = 'tblAgency';
	public $primaryKey   = 'agencyId';
	public $displayField = 'agencyName';

    public $hasMany = array(
		'Audit' => array(
			'foreignKey' => 'agencyId',
			'dependent'  => true,
			'exclusive'  => true
		),
		'AgencyCodeType' => array(
			'foreignKey' => 'agencyId',
			'dependent'  => true,
			'exclusive'  => true
		),
		'AgencyConfiguration' => array(
			'foreignKey' => 'agencyId',
			'dependent'  => true,
			'exclusive'  => true
		),
		'Device' => array(
			'foreignKey' => 'agencyId',
			'dependent'  => true,
			'exclusive'  => true
		),
		'FormGroup' => array(
			'foreignKey' => 'agencyId',
			'dependent'  => true,
			'exclusive'  => true
		),
		'Ip' => array(
			'foreignKey' => 'agencyId',
			'dependent' => true,
			'exclusive' => true
		),

        // Users refer to Groups, so User must appear before Group if $this->delete() is to function correctly.
		'User' => array(
			'foreignKey' => 'agencyId',
			'dependent'  => true,
			'exclusive'  => true
		),
		'Group' => array(
			'foreignKey' => 'agencyId',
			'dependent' => true,
			'exclusive' => true
		),

        // TODO: Responses refer to Request, HitType, and ResponseType, so Responses must appear before these items.
        'HitType' => array(
			'foreignKey' => 'agencyId',
			'dependent'  => true,
			'exclusive'  => true
		),
		'ResponseType' => array(
			'foreignKey' => 'agencyId',
			'dependent'  => true,
			'exclusive'  => true
		),
        
        // Response/Request information
        'ArchiveRequest' => array(
            'foreignKey' => 'agencyId',
            'dependent' => true,
            'exclusive' => true
        ),
        'ArchiveResponse' => array(
            'foreignKey' => 'agencyId',
            'dependent' => true,
            'exclusive' => true
        ),
        'Request' => array(
            'foreignKey' => 'agencyId',
            'dependent' => true,
            'exclusive' => true
        ),
        'Response' => array(
            'foreignKey' => 'agencyId',
            'dependent' => true,
            'exclusive' => true
        ),
	);

	public $validate = array(
		'interface' => array(
            'required' => array(
                'required' => 'create',
                'rule'     => 'notBlank',
                'message'  => 'Interface may not be blank when an agency is created'
            ),
            'format' => array(
                'rule'     => '/^[A-Z]{2}_[_A-Z0-9]+$/i',
                'message'  => 'You must specify a valid ConnectCIC formatted interface name'
            )
		),
		'agencyName' => array(
            'required' => array(
                'required' => 'create',
                'rule'     => 'notBlank',
                'message'  => 'Agency name may not be blank when an agency is created'
            ),
            'limited' => array(
                // (?=.*[[:alpha:]]) is a look ahead assertion that requires at least one alpha character.
                'rule'     => '/^(?=.*[[:alpha:]])[[:print:]]+$/i',
                'message'  => 'Agency name must contain at least one letter and may contain letters, numbers, and punctuation.'
            ),
            'unique' => array(
                'rule'    => 'isUnique',
                'message' => 'An agency of the specified name already exists'
            )
		),
		'agencyEnabled' => array(
            'required' => array(
                'required' => 'create',
                'rule'     => 'boolean',
                'message'  => 'Agency enabled must be specified when an agency is created'
            ),
            'boolean' => array(
    			'rule'     => 'boolean',
        		'message'  => 'Agency Enabled must have a value of 0, 1, true, or false'
            )
		),
	);
    
    public function createAgency($data=null, $options=array())
    {
        $this->clear();

        if (empty($data[$this->alias]))
            $data= array($this->alias => $data);

        if (empty($data[$this->alias]['interface']))
            $data[$this->alias]['interface'] = Configure::read('Clips.agency.interface');

        // Start the transaction
        $options = array_merge(array('atomic' => true), $options);
        if (!$this->transaction(null, $options['atomic']))
            return false;
        
        nullIfEmpty($data[$this->alias], 'description');
        
        // Save the agency data
        if (!$this->save($data))
            return $this->transaction(false, $options['atomic']);
        
        // Save the ORI drop down
        $this->AgencyCodeType->clear();
        $success = $this->AgencyCodeType->save(array(
            $this->AgencyCodeType->alias => array(
                'agencyId' => $this->id,
                'name' => '$_ORI_DD_$'
            )
        ));
        if (!$success)
            return $this->transaction(false, $options['atomic']);
        
        // Save the Administrator's Group
        $this->Group->clear();
        $success = $this->Group->save(array(
            $this->Group->alias => array(
                'agencyId' => $this->id,
                'groupName' => 'Administrator'
            )
        ));
        if (!$success)
            return $this->transaction(false, $options['atomic']);
        
        // Save the User admin
        $this->User->clear();
        $success = $this->User->save(array(
            $this->User->alias => array(
                'agencyId' => $this->id,
                'userName' => 'admin',
                'userFirstName' => 'Local',
                'userLastName' => 'Administrator',
                'userPassword' => 'admin',
                'confirmPassword' => 'admin'
            )
        ));
        if (!$success)
            return $this->transaction(false, $options['atomic']);

        // Assign administrative permissions to the created group.
        if (!$this->Group->assignAdministratorPermissions(array('atomic' => false)))
            return $this->transaction(false, $options['atomic']);

        // Assign the created user to the created group
        if (!$this->User->assignGroup($this->Group->id))
            return $this->transaction(false, $options['atomic']);

        return $this->transaction(true, $options['atomic']);
    }

    public function enable($id=null)
    {
        if (!empty($id))
        {
            $this->clear();
            $this->id = $id;
        }
        
        return $this->saveField('agencyEnabled', true);
    }

    public function disable($id=null)
    {
        if (!empty($id))
        {
            $this->clear();
            $this->id = $id;
        }

        return $this->saveField('agencyEnabled', false);
    }
}
