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

// Represents an endpoint device, or workstation, which will be used by users to access CLIPS and, possibly, run 
// transactions.
class Device extends AppModel
{
    public $useTable     = 'tblAgencyDevice';
    public $primaryKey   = 'agencyDeviceId';
    public $displayField = 'name';
    
    public $belongsTo = array(
        'Agency' => array(
            'foreignKey' => 'agencyId'
        )
    );

    // If you add new bit flags to 'options', be sure to update beforeSave()
    public $virtualFields = array(
        'twoFactor' => 'case when (options & 1) = 1 then 1 else 0 end',
        'prefill'   => 'case when (options & 2) = 2 then 1 else 0 end'
    );
    
    public $hasMany = array(
        'DeviceForward' => array(
            'foreignKey' => 'agencyDeviceId',
            'dependent'  => true,
            'exclusive'  => true
        ),
        'Request' => array(
            'foreignKey' => 'agencyDeviceId',
            'dependent'  => true,
            'exclusive'  => true
        ),
        'Response' => array(
            'foreignKey' => 'agencyDeviceId',
            'dependent'  => true,
            'exclusive'  => true
        ),
    );
    
    public $hasAndBelongsToMany = array(
        'Forward' => array(
            'className' => 'Device',
            'with' => 'DeviceForward',
            'foreignKey' => 'agencyDeviceId',
            'associationForeignKey' => 'destinationAgencyDeviceId'
        )
    );

    public $validate = array(
        'agencyId' => array(
            'rule' => array('rowExists', 'Agency.agencyId'),
            'message' => 'Agency ID must be the ID of an existing agency.'
        ),
        'deviceAlias' => array(
            'rule' => 'validateDeviceAlias',
            'allowEmpty' => true
        ),
        'computerName' => array(
            'rule' => 'notBlank',
            'message' => 'Computer name is a reqiured field.'
        ),
        'name' => array(
            'rule' => 'notBlank',
            'message' => 'Name is a required field.'
        )
    );
    
    public function beforeSave($options=array())
    {        
        // This are in bit order.
        $optionList = array('twoFactor','prefill');
        
        // Check to see if any options bits need to be set.
        $fields = array_keys($this->data[$this->alias]);
        $optionFields = array_intersect($fields, $optionList);
        
        if (!empty($optionFields))
        {
            $options = 0;
            // If options is explicitly set in the data array, just use it.
            if (isset($this->data[$this->alias]['options'])) {
                $options = $this->data[$this->alias]['options'];
            }
            // Otherwise if the number of options fields set is less than the available ones, we need to
            // read the current options value from the  database so we don't lose any set flags.
            else if (!empty($this->id) && count($optionFields) < count($optionList))
            {
                $device = $this->find('first', array(
                    'fields' => array('options'),
                    'conditions' => array(
                        $this->primaryKey => $this->id
                    )
                ));

                if ($device) {
                    $options = (int)$device[$this->alias]['options'];
                }
            }

            // Fix options bits based on the flags and clear the virtual field from $this->data
            foreach($optionList as $bit => $name)
            {
                if (!isset($this->data[$this->alias][$name])) {
                    continue;
                }
                
                $bitValue = (1 << $bit);
                if ($this->data[$this->alias][$name]) {
                    $options |= $bitValue;
                } 
                else {
                    $options &= ~$bitValue;
                }

                unset($this->data[$this->alias][$name]);
            }
            
            // Set the correct options value
            $this->data[$this->alias]['options'] = $options;
        }
                
        return parent::beforeSave($options);
    }
    
    public function enable($id)
    {
        if (!empty($id))
        {
            $this->clear();
            $this->id = $id;
        }
        
        $ds = $this->getDataSource();
        $ds->begin();
        
        if (!$this->saveField('deviceEnabled', true)) 
        {
            $ds->rollback();
            return false;
        }

        $this->read();
        if (!empty($this->data['Device']['deviceAlias']) &&
            !empty($this->data['Device']['ori']) &&
            !empty($this->data['Device']['mnemonic']) &&
            !empty($this->data['Device']['deviceId']))
        {
            $this->Agency->read(null, $this->data[$this->alias]['agencyId']);

            $da = ClassRegistry::init('DeviceAlias');
            if (!$da->saveIdentifiers($this->Agency->data['Agency']['interface'], 
                                      $this->data['Device']['deviceAlias'],
                                      $this->data['Device']['ori'],
                                      $this->data['Device']['mnemonic'],
                                      $this->data['Device']['deviceId'],
                                      array('atomic' => false)))
            {
                $ds->rollback();
                return false;
            }
        }
        
        $ds->commit();
        return true;
    }

    public function disable($id)
    {
        if (!empty($id))
        {
            $this->clear();
            $this->id = $id;
        }
        
        $ds = $this->getDataSource();
        $ds->begin();

        if (!$this->saveField('deviceEnabled', false)) 
        {
            $ds->rollback();
            return false;
        }

        $this->read();
        if (!empty($this->data['Device']['deviceAlias'])) 
        {
            $this->Agency->read(null, $this->data[$this->alias]['agencyId']);

            $da = ClassRegistry::init('DeviceAlias');
            if (!$da->deleteIdentifiers($this->Agency->data['Agency']['interface'], 
                                        $this->data['Device']['deviceAlias'],
                                        array('atomic' => false)))
            {
                $ds->rollback();
                return false;
            }
        }
        
        $ds->commit();
        return true;
    }

    public function saveDevice($data=null, $options=array())
    {
        $this->set($data);
        $deviceAlias = $this->deviceAlias();
        
        // Assign a device id if a mnemonic or ori is assigned.
        if (!empty($this->data[$this->alias]['ori']) || !empty($this->data[$this->alias]['mnemonic']))
        {
            if (empty($this->data[$this->alias]['deviceId']))
                $this->data[$this->alias]['deviceId'] = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $deviceAlias));
        }
        else
            $this->data[$this->alias]['deviceId'] = null;

        // Null out the ORI, Mnemonic, and DeviceId if their values are empty.
        nullIfEmpty($this->data[$this->alias], 'ori');
        nullIfEmpty($this->data[$this->alias], 'mnemonic');
        nullIfEmpty($this->data[$this->alias], 'deviceId');

        // Presently, the device name and device alias can't be changed after it's been created.  So remove those.
        if ($this->id || !empty($this->data[$this->alias]['agencyDeviceId']))
        {
            unset($this->data[$this->alias]['name']);
            unset($this->data[$this->alias]['deviceAlias']);
        }
        // Otherwise set the device alias so that we always have it.
        else
            $this->set('deviceAlias', $deviceAlias);

        // Start the transaction
        $options = array_merge(array('atomic' => true), $options);
        if (!$this->transaction(null, $options['atomic']))
            return false;

        // Save the device information
        if (!($data = $this->save()))
            return $this->transaction(false, $options['atomic']);
        
        // Read the interface for the agency this device belongs to
        $this->Agency->id = ((empty($data[$this->alias]['agencyId'])) 
            ? $this->field('agencyId') 
            : $data[$this->alias]['agencyId']);
        $interface = $this->Agency->field('interface');

        // Start a transaction on the Alias database
        $daTransStarted = false;
        $da = ClassRegistry::init('DeviceAlias');
        if (!($da->transaction(null, true)))
            return $this->transaction(false, $options['atomic']);

        // Delete all identifiers
        if (!$da->deleteIdentifiers($interface, $deviceAlias))
        {
            $da->transaction(false, true);
            return $this->transaction(false, $options['atomic']);
        }

        // Save new identifiers
        if (!empty($data[$this->alias]['deviceEnabled']))
        {
            if (!empty($data[$this->alias]['ori']) &&
                !empty($data[$this->alias]['mnemonic']) &&
                !empty($data[$this->alias]['deviceId']))
            {
                $success = $da->saveIdentifiers($interface, $deviceAlias, 
                    $data[$this->alias]['ori'],
                    $data[$this->alias]['mnemonic'],
                    $data[$this->alias]['deviceId'],
                    array('atomic' => true));

                if (!$success)
                {
                    $da->transaction(false, true);
                    return $this->transaction(false, $options['atomic']);
                }
            }
        }

        // Commit to the alias database.  if it fails, rollback all transactions.
        if (!$da->transaction(true, true))
            return $this->transaction(false, $options['atomic']);

        // Comit to the CLIPS database.
        return $this->transaction(true, $options['atomic']);
    }
    
    public function deleteDevice($id=null)
    {
        if (!$id)
        {
            if (empty($this->id))
                throw new InvalidArgumentException();
            $id = $this->id;
        }

        // Read the device and agency information 
        $this->read(array('agencyId', 'deviceAlias'), $id);
        $this->Agency->id = $this->data[$this->alias]['agencyId'];
        $interface = $this->Agency->field('interface');

        // Start the transactions
        $options = array_merge(array('atomic' => true), $options);
        if (!$this->transaction(null, $options['atomic']))
            return false;

        $da = ClassRegistry::init('DeviceAlias');
        if (!$da->transaction(null, true))
            return $this->transaction(false, $options['atomic']);

        // Delete the alias identifiers
        if (!$da->deleteIdentifiers($interface, $this->data[$this->alias]['deviceAlias']))
        {
            $da->transaction(false, true);
            return $this->transaction(false, $options['atomic']);
        }

        // Delete the device
        if (!$this->delete($id))
        {
            $da->transaction(false, true);
            return $this->transaction(false, $options['atomic']);
        }
        
        // Commit the transaction
        if (!$da->transaction(true, true))
            return $this->transaction(false, $options['atomic']);
        
        return $this->transaction(true, $options['atomic']);
    }
    
    public function deviceAlias($data=null)
    {
        if (empty($data))
        {
            $data = $this->data;
            if (empty($data[$this->alias][$this->primaryKey]) && $this->id)
                $data[$this->alias][$this->primaryKey] = $this->id;
        }

        if (!empty($data[$this->alias]))
            $data = $data[$this->alias];
        
        if (!empty($data['deviceAlias']))
            return $data['deviceAlias'];
        
        if (empty($data['agencyId']) || empty($data['name']))
        {
            if (!empty($data[$this->primaryKey]))
            {
                if (($deviceAlias = $this->field('deviceAlias', array($this->primaryKey => $data[$this->primaryKey]))))
                    return $deviceAlias;
            }
            
            return null;
        }

        return $data['agencyId'] . ':' . $data['name'];
    }

    public function validateDeviceAlias($check)
    {
        $value = current($check);
        $expected = $this->data[$this->alias]['agencyId'] . ':' . $this->data[$this->alias]['name'];
        if (!Validation::equalTo($value, $expected))
            return "Device Alias expected to be '$expected'.";

        return true;
    }
    
    public function validatePrefill($check)
    {
        // If we're not prefilling, nothing to do.
        $value = current($check);
        if (!$value) {
            return true;
        }
        
        // Otherwise, find out if this device already had prefill
        if (!empty($this->id))
        {
            $device = $this->find('first', array(
                'fields' => array('prefill'),
                'conditions' => array(
                    $this->primaryKey => $this->id
                )
            ));
            
            // Prefill is already set, so nothing changed. Allow the save.
            if ($device[$this->alias]['prefill']) {
                return true;
            }
        }
        
        return true;
    }
}
