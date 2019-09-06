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
App::uses('CurrentAgency', 'Model/Datasource');

class CurrentAgencyComponent extends Component
{
    public static function registered()
    {
        return CurrentAgency::registered();
    }

	public function __get($key)
	{
        $value = CurrentAgency::agency($key);
		if ($value === null)
			return parent::__get($key);
		return $value;
	}
    
    public function __isset($key)
    {
        if (!CurrentAgency::registered())
            return false;
        
        $value = CurrentAgency::agency($key);
        return !empty($value);
    }

    public function actual($key)
    {
        return CurrentAgency::agency($key, true);
    }
    
    public static function register($agencyId)
    {
        return CurrentAgency::register($agencyId);
    }
    
	public static function impersonate($agencyId)
	{
        CurrentAgency::impersonate($agencyId);
	}

    public static function clear()
    {
        CurrentAgency::clear();
    }

    public static function impersonating()
    {
        return CurrentAgency::impersonating();
    }
}
