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
App::uses('FindUserAuthenticate', 'Controller/Component/Auth');

AuditLog::Define(array(
    'AUDIT_CREATE_USER_FAILED' => array(
        'Auto-create Failed',
        'Failed to create user account record from 3rd party system data'
    ),
    'AUDIT_CREATE_USER_NO_MEMBERSHIP' => array(
        'Auto-create Failed',
        'Failed to create user because they do not belong to any permission groups'
    ),
    'AUDIT_CREATE_USER_MEMBERSHIP_FAILURE' => array(
        'Auto-create Failed',
        'Failed to create user account memberships'
    )
));

class CreateUserAuthenticate extends FindUserAuthenticate
{
    public $clips_policies = array(
        'enforce_password_policies' => false,
        'change_password'           => false,
        'manage_users'              => false
    );

    public function authenticate(CakeRequest $request, CakeResponse $response)
    {
        if (empty($this->data))
            return false;

        $user = $this->data;
        $memberships = false;

        // If memberships is set, store those.
        if (isset($user['memberships']))
        {
            $memberships = $user['memberships'];
            unset($user['memberships']);
        }

        if (empty($request->data['Agency']['agencyId']))
            unset($user['agencyId']);
        else
        {
            $user['agencyId'] = $request->data['Agency']['agencyId'];
            $user['userAlias'] = $user['agencyId'] . ':' . $user['userName'];
        }
        
        $user = array_merge(array(
            'userEnabled' => 1,
            'forceExpire' => 0,
            'loginAttempt' => 0,
            'isPowerUser' => 0
        ), $user);
        
        unset($user['userPassword']);
        unset($user['userAlias']);
        unset($user['userFullName']);
        unset($user['userFullNameLast']);

        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'CreateUserAuthenticate::authenticate() user from other auth systems: ';
            print_r($user);
            echo "\r\n";
            if ($memberships !== false)
            {
                echo 'CreateUserAuthenticate::authenticate() memberships from other auth systems: ';
                print_r($memberships);
                echo "\r\n";
            }
            cs_debug_end_capture();
        }

        if (empty($user['agencyUserId']))
        {
            $user['userPassword'] = $request->data['User']['userPassword'];
            $user['confirmPassword'] = $request->data['User']['userPassword'];
        }
        
        $userId = $this->createUserAccount($user);
        if ($userId === false)
        {
            AuditLog::Log(AUDIT_CREATE_USER_FAILED);
            CakeLog::write('error', 'Failed to create user account from 3rd party system data');
            return false;
        }
        
        $user['agencyUserId'] = $userId;
        unset($user['userPassword']);
        unset($user['confirmPassword']);

        if (is_array($memberships))
        {
            if (empty($memberships))
            {
                AuditLog::Log(AUDIT_CREATE_USER_NO_MEMBERSHIP);
                CakeLog::write('error', 'Failed to update user account memberships because the user ' .
                                        'does not belong to any permission groups.');
                return false;
            }
            else if (!$this->createMemberships($user, $memberships))
            {
                AuditLog::Log(AUDIT_CREATE_USER_MEMBERSHIP_FAILURE);
                CakeLog::write('error', 'Failed to update user account memberships');
                return false;
            }
        }

        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'CreateUserAuthenticate::authenticate() user after create: ';
            print_r($user);
            echo "\r\n";
            if ($memberships !== false)
            {
                echo 'CreateUserAuthenticate::authenticate() memberships after create: ';
                print_r($memberships);
                echo "\r\n";
            }
            cs_debug_end_capture();
        }

        // Re-load the user from the database. This ensures all custom fields are properly read.
        $user = parent::authenticate($request, $response);
        
        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'CreateUserAuthenticate::authenticate() user after load from DB: ';
            print_r($user);
            echo "\r\n";
            cs_debug_end_capture();
        }
        
        return $user;
    }

    private function createUserAccount($data)
    {
        $um = ClassRegistry::init('User');
        
        // Update instead of create?
        if (!empty($data['agencyUserId']))
            $um->id = $data['agencyUserId'];
        
        if (!$um->saveUser($data, array('noexpire' => true))) {
            $str = '';
            foreach($um->validationErrors as $field => $errors)
            {
                foreach($errors as $error)
                    $str.= "$field: $error\r\n";
            }

            CakeLog::write('error', 'User account failed: ' . $str);
            return false;
        }
        
        return $um->id;
    }

    private function createMemberships($user, $memberships)
    {
        $up = ClassRegistry::init('UserPermission');

        $up->deleteAll(array(
            'agencyUserId' => $user['agencyUserId']
        ));
        $data = array();
        foreach(array_keys($memberships) as $groupId)
        {
            $data[] = array(
                'agencyUserId' => $user['agencyUserId'],
                'agencyGroupId' => $groupId
            );
        }
        
        if (!$up->saveAll($data))
            return false;

        return true;
    }
}
