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
App::uses('Component', 'Controller');
App::uses('CurrentDevice', 'Model/Datasource');

class CurrentDeviceComponent extends Component
{  
    public function registered()
    {
        return CurrentDevice::registered();
    }

	public function __get($key)
	{
        $value = CurrentDevice::device($key);
		if ($value === null)
			return parent::__get($key);

		return $value;
	}
    
    public function __isset($key)
    {
        if (!CurrentDevice::registered())
            return false;
        
        $value = CurrentDevice::device($key);
        return !empty($value);
    }
    
    public function stateid($name=null)
    {
        return CurrentDevice::stateid($name);
    }
    
    public function register($agencyId, $clientIp, $endpoint)
    {
        CurrentDevice::register($agencyId, $clientIp, $endpoint);
    }

    public function isTransactionReady()
    {
        return CurrentDevice::transactionReady();
    }
}
