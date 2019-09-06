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
App::uses('FindUserAuthenticate', 'Controller/Component/Auth');

class ClipsAuthenticate extends FindUserAuthenticate
{
    public function __construct(ComponentCollection $collection, $settings=array())
    {
        // Setup default settings.
        $default = array(
            'passwordHasher' => 'Clips'
        );
        $settings = Hash::merge($default, $settings);

        parent::__construct($collection, $settings);
    }
    
    // Override _findUser to impose our own user conditions and login attempts.
    protected function _findUser($username, $password = null) 
    {
        $user = parent::_findUser($username, $password);
        if (empty($user) || !is_array($user))
        {
            AuditLog::Log(AUDIT_FAILED_LOGIN, $username, CurrentDevice::device('name'));
            return false;
        }
        
        $maxAttempts = Configure::read('Clips.agency.password.maxattempts');

        if ($this->passwordHasher()->check($password, $user['userPassword']))
        {
            // Reset the login account.
            // TODO: Make this a model function (like loginAttempt())
            if ($maxAttempts > 0)
            {
                ClassRegistry::init('User')->save(array(
                    'agencyUserId' => $user['agencyUserId'],
                    'loginAttempt' => 0
                ));
            }

            // Check for password expiration; Set the forceExpire flag to 1 if so.  This
            // will trigger a change_password request during authorization
            $expireDays = Configure::read('Clips.agency.password.lifetime');
            if ($expireDays > 0)
            {
                $date = strtotime($user['passwordSetDate']);
                $expires = $date + ($expireDays * 24 * 60 * 60);
                
                if (time() > $expires)
                    $user['forceExpire'] = 1;
            }
            
            unset($user['userPassword']);
            return $user;
        }
        
        AuditLog::log(AUDIT_INVALID_PASSWORD, $username);

        // TODO: Make this an authentication configuration parameter.
        if ($maxAttempts > 0)
        {
            if (ClassRegistry::init('User')->loginAttempt($maxAttempts, $user['agencyUserId']) == false)
                AuditLog::Log(AUDIT_TOO_MANY_LOGINS, $username);
        }
        
        return false;
    }
}
