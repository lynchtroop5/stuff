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
App::uses('AuthComponent', 'Controller/Component');
App::uses('CakeSession', 'Model/Datasource');

class CurrentUser
{
	private static $forms = null;
	private static $permissions = null;
	private static $isAdmin = null;
	private static $isEnabled = null;
	private static $ignoreAdminPermissions = array(
        'searchCriminalHistory',
        'searchRequestHistory', 
        'searchResponseHistory'
    );

	public static function user($key=null)
	{
		if ($key == 'userEnabled') 
		{
			if (empty(AuthComponent::user('agencyUserId'))) {
				return true;
			}

			if (self::$isEnabled === null) 
			{
				$user = ClassRegistry::init('User');
				$user->id = self::user('agencyUserId');
				$user->read(array('userEnabled'));
				self::$isEnabled = (bool)$user->data['User']['userEnabled'];
			}

			return self::$isEnabled;
		}
		return AuthComponent::user($key);
	}

	public static function loggedIn()
	{
		return (AuthComponent::user() != array());
	}
	
	public static function permissions()
	{
		if (self::$permissions === null)
		{
			$user = ClassRegistry::init('User');
			$permissions = $user->getPermissions(self::user('agencyUserId'));

			self::$permissions = array();
			foreach($permissions as $permission => $isAdmin)
			{
                // Disable the admin flag for permissions in the ignoreAdminPermissions array.
                $isAdmin = $isAdmin && !in_array($permission, self::$ignoreAdminPermissions);

				self::$isAdmin = (self::$isAdmin || $isAdmin);
				self::$permissions[] = $permission;
			}
		}
		
		return self::$permissions;
	}

	public static function forms()
	{
		if (self::$forms === null)
		{
			$user = ClassRegistry::init('User');
			self::$forms = Hash::extract($user->getAllForms(), '{n}.ConnectCicForm.formName');
		}

		return self::$forms;
	}

	public static function isSystemAdmin()
	{
		return ((int)self::user('agencyId') === 0 && (int)self::user('isPowerUser') === 1);
	}
	
	public static function isAgencyAdmin()
	{
		if (self::$isAdmin === null)
			self::permissions();  // Load the permissions

		return (self::loggedIn() && (self::isSystemAdmin() || self::$isAdmin));
	}

    // Returns true if the user has the requested permission(s).  $name may be a single permission name by string or
    // an array of permissions.  If $requireAll is true, all permissions in $name must be found or the routine will
    // return false.
	public static function hasPermission($name, $requireAll=false)
	{
		if (self::isSystemAdmin())
			return true;

		if (is_string($name))
            $name = array($name);

		$break = !$requireAll;
        $permissions = self::permissions();
		foreach($name as $permission)
		{
			if (in_array($permission, self::permissions()) == $break)
				return $break;
		}

		return !$break;
	}

	public function hasAccessToForm($formName)
	{
		if (!self::canRunTransactions()) {
			return false;
		}
		$forms = self::forms();
		return in_array($formName, $forms);
	}

    public static function canRunTransactions()
    {
        return (!self::isSystemAdmin() && self::hasPermission('runTransaction'));
    }
}
