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
App::uses('CurrentUser', 'Model/Datasource');

class CurrentUserComponent extends Component
{
	public function __get($key)
	{
        $value = CurrentUser::user($key);
		if ($value === null)
			return parent::__get($key);

		return $value;
	}
    
    public function __isset($key)
    {
        if (!CurrentUser::loggedIn())
            return false;
        
        $value = CurrentUser::user($key);
        return !empty($value);
    }
	
	public function loggedIn()
	{
		return CurrentUser::loggedIn();
	}
		
	public function permissions()
	{
		return CurrentUser::permissions();
	}

	public function isSystemAdmin()
	{
		return CurrentUser::isSystemAdmin();
	}
	
	public function isAgencyAdmin()
	{
		return CurrentUser::isAgencyAdmin();
	}

	public function hasPermission($name, $requireAll=false)
	{
		return CurrentUser::hasPermission($name, $requireAll);
	}

	public function hasAccessToForm($formName)
	{
		return CurrentUser::hasAccessToForm($formName);
	}

    public function canRunTransactions()
    {
        return CurrentUser::canRunTransactions();
    }
}
