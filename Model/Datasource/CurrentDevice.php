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
App::uses('CakeSession', 'Model/Datasource');
App::uses('CurrentUser', 'Model/Datasource');

class CurrentDevice
{
    private static $isUpdated = false;
    private static $stateIds = array('ori', 'mnemonic', 'deviceId');

    public static function update() 
    {
        if (self::$isUpdated === true) {
            return;
        }

        if (empty(self::Device('agencyDeviceId'))) {
            return;
        }

        self::$isUpdated = true;

        $d = ClassRegistry::init('Device');
        $d->id = self::device('agencyDeviceId');
        $d->read(array('deviceEnabled'));

        CakeSession::write('Device.deviceEnabled', $d->data['Device']['deviceEnabled']);
    }

    public static function registered()
    {
        // The device is registered if we have an agency device ID for the device.
        return CakeSession::check('Device.agencyDeviceId');
    }

	public static function device($key=null)
	{
        if ($key == 'deviceEnabled' && self::registered() == false) {
            return true;
        }

        $sessionKey = 'Device';
        if ($key != null)
            $sessionKey.= '.' . $key;
        
        if (!CakeSession::check($sessionKey))
            return null;

		return CakeSession::read($sessionKey);
	}
    
    public static function stateid($name=null)
    {
        if (!self::registered())
            return null;

        if ($name != null)
        {
            if (!in_array($name, self::$stateIds))
                return null;
         
            return self::device($name);
        }

        // Extract only registered state identifiers for this device.
        $ids = array_intersect_key(self::device(), array_flip(self::$stateIds));
        if (empty($ids))
            return null;
        
        return $ids;
    }
    
    // Locates and registers the device information.  The device information is found in the CLIPS database by 
    // searching in the agency specified by $agencyId.  The device is located first by $endpoint name.  If the 
    // endpoint name is empty or the device couldn't be found, the device is located by $clientIp.
    //
    // Even if the registered() method returns false, the device clientIp and computerName will always be 
    // available from the device() method.  These values are set based on the following rules:
    //
    // - device('clientIp') will always be $clientIp passed to this method.
    // - device('computerName') will be $endpoint if $endpoint is not empty and a device was found by $endpoint.
    //   Otherwise, it will be $clientIp
    public static function register($agencyId, $clientIp, $endpoint)
    {
        if (self::registered())
            return;
            
        $device = array(
            'clientIp' => $clientIp,
            'computerName' => ((!empty($endpoint)) ? $endpoint : $clientIp)
        );

        $dm = ClassRegistry::init('Device');
        $options = array(
            'fields' => array('agencyDeviceId', 'name', 'deviceAlias', 'computerName', 'twoFactor', 'prefill',
                              'ori', 'mnemonic', 'deviceId', 'deviceType', 'deviceEnabled'),
            'conditions' => array(
                'agencyId' => $agencyId,
                'computerName' => null
            )
        );

        // First search by computer name, if one was passed
        if (!empty($endpoint))
        {
            $options['conditions']['computerName'] = $endpoint;
            $result = $dm->find('first', $options);
        }

        // If that fails, search by IP Address
        if (empty($result))
        {
            $options['conditions']['computerName'] = $clientIp;
            $result = $dm->find('first', $options);
        }
        
        // If we found something, merge the results with our device array
        if (!empty($result))
        {
            $device = array_merge($device, $result['Device']);

            unsetIfEmpty($device, 'ori');
            unsetIfEmpty($device, 'mnemonic');
            unsetIfEmpty($device, 'deviceId');
        }

        CakeSession::write('Device', $device);
        return !empty($result);
    }

    public static function isTransactionReady()
    {
        return (self::registered() && self::stateid());
    }
}
