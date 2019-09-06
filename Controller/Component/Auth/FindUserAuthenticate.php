<?php
/*******************************************************************************
 * Copyright 2014 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 ******************************************************************************/
App::uses('AuditLog', 'Lib');
App::uses('CurrentAgency', 'Model/Datasource');
App::uses('FormAuthenticate', 'Controller/Component/Auth');

// WARNING: This authentication object does NOT do password verification.  It is used simply to find the
//          user record within the CLIPS database.  It is expected that the inheriting class validate the
//          user's password.
class FindUserAuthenticate extends FormAuthenticate
{
    public $clips_policies = array(
        // Enables enforcing password policies and shows/hides password policy settings in the configuration screen.
        'enforce_password_policies' => true,

        // Enables displaying of "change password" tab and password fields on user edit screens.
        'change_password'           => true,
        
        // Enables displaying of two-factor field on the login screen.
        'two_factor'                => true,
        
        // Enables the ability to create/manage users.  This should be disabled if part of the authentication step
        // generates a user record based on external sources.
        'manage_users'              => true
    );
    
    public function __construct(ComponentCollection $collection, $settings=array())
    {
        $this->mergePolicies();

        // Enforce model settings
        $modelSettings = array(
            'userModel' => 'User',
            'recursive' => -1,
            'fields' => array(
                'username' => 'userName',
                'password' => 'userPassword'
            ),
        );
        $settings = Hash::merge($settings, $modelSettings);

        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'Find User Settings: ';
            print_r($settings);
            echo "\r\n";
            cs_debug_end_capture();
        }

        parent::__construct($collection, $settings);
    }

    public function authenticate(CakeRequest $request, CakeResponse $response)
    {
        // Store these as member values so _findUser can access them.
        $this->request = $request;
        $this->response = $response;

        return parent::authenticate($this->request, $this->response);
    }
    
    // Override _findUser to simply find the user account.  Password management is handled in the user configured
    // authentication handler.
	protected function _findUser($username, $password = null) 
    {
        // Add agency constraints on the user logging in
        if (!is_array($username))
        {
            $conditions = array('userEnabled' => 1);
            
            if (!empty($this->request->data['Agency']['agencyId']))
                $conditions['User.agencyId'] = $this->request->data['Agency']['agencyId'];
            else
                $conditions[] = "User.agencyId is null";

            $conditions['User.userName'] = $username;
        }
        else
            $conditions = $username;
        
        // If the first parameter of _findUser is an array, it assumes it's the WHERE clause of the
        // user lookup.  We don't pass the password because we need to verify it manually.  Otherwise,
        // we won't be able to correctly process login attempts
        $user = parent::_findUser($conditions, null);
        if (empty($user) || !is_array($user))
        {
            if (Configure::read('debug') > 1)
                cs_debug_write ("FindUserAuthenticate::_findUser() result is no user\r\n");
            
            return false;
        }

        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'FindUserAuthenticate::_findUser() result is ';
            print_r($user);
            echo "\r\n";
            cs_debug_end_capture();
        }

        return $user;
    }

    private function mergePolicies()
    {
        $policies = array();
        
        $vars = get_class_vars('FindUserAuthenticate');
        if (!empty($vars['clips_policies']))
            $policies = $vars['clips_policies'];
        
        if (!empty($this->clips_policies))
            $policies = array_merge($policies, $this->clips_policies);
        
        $this->clips_policies = $policies;
    }
}
