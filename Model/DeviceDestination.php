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

class DeviceDestination extends AppModel
{
    public $useDbConfig  = 'connectcic';
	public $useTable     = 'tblConnectCicDeviceDestination';
	public $primaryKey   = 'deviceDestinationIdentity';

    public function saveMapping($interface, $destinationAlias, $deviceAlias)
    {
        $data = array(
            'interface' => $interface,
            'destinationAlias' => $destinationAlias,
            'deviceAlias' => $deviceAlias
        );

        if ($this->hasAny($data))
            return true;

        $data = array_merge($data, array(
            'trustedConnection' => 1,
            'sendTransInformMsgs' => 1,
            'maxMessageSize' => 65536,
            'isDefault' => 1
        ));

        return $this->save($data);
    }

    public function deleteMapping($interface, $destinationAlias, $deviceAlias)
    {
        $data = array(
            'interface' => $interface,
            'destinationAlias' => $destinationAlias,
            'deviceAlias' => $deviceAlias
        );

        return $this->deleteAll($data);
    }
}
