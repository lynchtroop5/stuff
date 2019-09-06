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

class DeviceAlias extends AppModel
{
    public $useDbConfig  = 'connectcic';
	public $useTable     = 'tblConnectCicDeviceAlias';
	public $primaryKey   = 'deviceAliasIdentity';

    public function saveIdentifiers($interface, $deviceAlias, $ori, $mnemonic, $deviceId, $options=array())
    {
        // Load any previous identifiers that may exist.
        $existingAliases = $this->find('all', array(
            'fields' => array('deviceAliasIdentity', 'deviceType'),
            'conditions' => array(
                'interface' => $interface,
                'deviceAlias' => $deviceAlias
            ))
        );
        
        // Convert the results from find's format.  We're making the device type a key to the data instead of a numeric
        // index
        $existingAliases = Set::combine($existingAliases, '{n}.DeviceAlias.deviceType', '{n}.DeviceAlias');

        // Store the new values by type.
        $values = array(
            'Authentication/ORI' => $ori,
            'Authentication/Mnemonic' => $mnemonic,
            'Authentication/DeviceId' => $deviceId
        );
        
        // Data structure which will be used to store the new alias values.
        $data = array(
            'deviceAlias' => $deviceAlias,
            'interface' => $interface,
            'deviceType' => null,
            'device' => null
        );

        $options = array_merge(array('atomic' => true), $options);
        if (!$this->transaction(null, $options['atomic']))
            return false;

        foreach($values as $type => $value)
        {
            unset($data['deviceAliasIdentity']);
            if (isset($existingAliases[$type]['deviceAliasIdentity']))
            {
                if (empty($value))
                {
                    if (!$this->delete($existingAliases[$type]['deviceAliasIdentity']))
                        return $this->transaction(false, $options['atomic']);
                    continue;
                }

                $data['deviceAliasIdentity'] = $existingAliases[$type]['deviceAliasIdentity'];
            }

            // Otherwise, save the new value.
            $data['deviceType'] = $type;
            $data['device'] = $value;

            $this->clear();
            if (!$this->save($data, false))
                return $this->transaction(false, true);
        }

        $dd = ClassRegistry::init('DeviceDestination');
        $dd->saveMapping($interface, 'CLIPS_SERVER', $deviceAlias);

        return $this->transaction(true, $options['atomic']);
    }

    public function deleteIdentifiers($interface, $deviceAlias, $options=array())
    {
        $options = array_merge(array('atomic' => true), $options);
        if (!$this->transaction(null, $options['atomic']))
            return false;

        if (!ClassRegistry::init('DeviceDestination')->deleteMapping($interface, 'CLIPS_SERVER', $deviceAlias))
            return $this->transaction(false, $options['atomic']);
            
        $success = $this->deleteAll(array(
            'interface' => $interface,
            'deviceAlias' => $deviceAlias
        ), false);
        if (!$success)
            return $this->transaction(false, $optionst['atomic']);

        return $this->transaction(true, $options['atomic']);
    }
}
