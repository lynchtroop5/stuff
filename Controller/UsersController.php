<?php
/* * *****************************************************************************
 * Copyright 2012 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 * **************************************************************************** */
App::uses('HttpSocket', 'Network/Http');

class UsersController extends AppController
{
    public $components = array('Filter' => array(
            'datetime' => null,
            'fields' => array(
                'userName' => array('comparison' => 'like'),
                'userFirstName' => array('comparison' => 'like'),
                'userLastName' => array('comparison' => 'like'),
                'stateUserId' => array('comparison' => 'like'),
                'userEnabled'
            )
        ));

    public function __construct($request, $response)
    {
        if ($request->params['action'] == 'do_login')
            cs_debug_begin_write_session();

        parent::__construct($request, $response);
    }
    
    public function beforeFilter()
    {    
        // Login, logout, do_login, and test_two_factor do not need authorization tests.
        $this->Auth->allow(array('login', 'logout', 'do_login', 'test_two_factor'));

        // If an agency is registered, AppController will take care of loading the correct configuration.
        // However, if the user is attempting to login, no agency is registered and we have to manually load
        // the correct configuration.
        if ($this->request->is('post') && $this->request->params['action'] == 'do_login')
        {
            $data = $this->request->data;
            if (!empty($data['Agency']['agencyId']))
            {
                $this->configure($data['Agency']['agencyId']);
            }
        }
        
        if (!$this->Filter->Check('userEnabled'))
            $this->Filter->Write('userEnabled', true);
        
        parent::beforeFilter();
    }

    public function login()
    {
        if ($this->CurrentUser->loggedIn() && !$this->Session->read('Reauthenticate'))
            return $this->redirect(array('action' => 'logout'), null, true);

        $this->set('reauthenticate', $this->Session->read('Reauthenticate'));

        // Retrieve a list of agencies, filterd by whether or not they're enabled and whether or not the client's IP
        // is in the list of IP addresses allowed by that agency.
        $agencies = $this->User->Agency->find('list', array(
                'conditions' => array(
                    'agencyId in (select a.agencyId from tblAgency a ' .
                    'inner join tblAgencyIp i on a.agencyId = i.agencyId ' .
                    'where a.agencyEnabled = 1 ' .
                    " and dbo.TEST_IP_MASK('{$this->request->clientIp()}', i.ipAddress, i.ipMask) = 1)"
                )
            ));

        // Load two factor device information.  This is used to determine when the two-factor token input box should
        // be shown on screen.
        $twoFactor = array();
        /*
        $conf = Configure::read('Clips.agency.twoFactor');
        if (!empty($conf['authenticator']) && $conf['authenticator'] != 'none')
        {
            $twoFactor = $this->User->Agency->find('all', array(
                    'contain' => array(
                        'Device' => array(
                            'fields' => array('computerName'),
                            'conditions' => array(
                                'Device.twoFactor' => 1
                            )
                        )
                    ),
                    'fields' => array('Agency.agencyId'),
                    'conditions' => array(
                        'Agency.agencyEnabled' => 1
                    )
                ));
            $twoFactor = Set::combine($twoFactor, '{n}.Agency.agencyId', '{n}.Device.{n}.computerName');
        }
        */

        $agencyId = $this->defaultAgency();
        if (!empty($this->request->params['named']['agencyId']) &&
            is_numeric($this->request->params['named']['agencyId']))
            $agencyId = $this->request->params['named']['agencyId'];

        $this->set('agencyId', $agencyId);
        $this->set(compact('agencies', 'twoFactor'));

        $this->layout = 'login';
    }

    public function do_login()
    {
        if ($this->request->is('post'))
        {
            $request = $this->request->data;
            $reauthenticate = $this->Session->read('Reauthenticate');

            // Remember the previously selected agency id to make the user's life easier.
            if (!$reauthenticate)
                $this->defaultAgency($request['Agency']['agencyId']);
            else
            {
                $request['Agency']['agencyId'] = CurrentAgency::agency('agencyId');
                $request['User']['userName'] = CurrentUser::user('userName');
            }

            // Verify we got the POST data required from the ActiveX control.
            // Also allows mobile users to login.
            // if (empty($request['Device']['endpoint']) && !$this->Mobile->isMobile())
            // {
            //     $this->log('ERROR: CLIPS authentication plugin did not provide device information.');
            //     $this->Session->setFlash('ERROR: CLIPS authentication plugin did not provide device information. ' .
            //             'Verify that your browser is in compatibility mode and that the Status is set to "Ready."');
            //     $this->logout();
            // }
            
            // Load the agency & device information
            $agencyId = null;
            if (!empty($request['Agency']['agencyId']))
            {
                // Attempt to load the agency details.
                if (!$this->CurrentAgency->register($request['Agency']['agencyId']))
                {
                    $this->log('User login failed due to agency registration failure.');
                    $this->Session->setFlash('Username or password is incorrect.');
                    $this->logout();
                }

                $agencyId = $this->CurrentAgency->agencyId;
            }

            // Load the device information
            $endpoint = ((!empty($request['Device']['endpoint'])) ? $request['Device']['endpoint'] : null);
            $this->CurrentDevice->register($agencyId, $this->request->clientIp(), $endpoint);

            // Authenticate the user.  If this fails, the auth component is responsible for logging the 
            // reason to the AuditLog.
            if (!$this->Auth->login())
            {
                $this->log('User login failed due to user authentication failure.');
                if (empty($this->Auth->loginError))
                    $this->Session->setFlash('Username or password is incorrect.');
                else
                    $this->Session->setFlash($this->Auth->loginError);
                $this->logout();
            }

            // HACK: This is to detect a scenario where the user session information is not being correctly loaded.
            //       Hopefully some logging that has occurred before this will help track it down.
            $dumpSession = false;
            if (empty($this->CurrentUser->userFullName))
            {
                $this->log('User information not correctly loaded from session.');
                $this->Session->setFlash('User information not correctly loaded from session. Please contact your ' .
                                         'support desk to report the error and try logging in again.');
                $dumpSession = true;
            }
            
            if ($dumpSession == true || Configure::read('Clips.debug.login') != 0)
            {
                cs_debug_begin_capture();
                echo 'Session Data (' . $this->Session->id() . '): ';
                print_r($this->Session->read());
                echo "\r\n";
                $data = cs_debug_get(); // Also ends capturing.
                $this->log('User session information: ' . $data, LOG_DEBUG);
            }

            if ($dumpSession == true)
                $this->redirect('/');
            
            $adminIp = ClassRegistry::init('AdminIp');
            // NOTE: This is the legacy interface path, not the new Interfaces.<interface> path.
            //       This is because the AZ requireStateUserId field hasen't been moved, yet.
            $interfaceConfigPath = "Clips.interface.{$this->CurrentAgency->interface}";
            
            // Check if the user is an systems administrator and if they can login from this location.
            if ($this->CurrentUser->isSystemAdmin() &&
                !$adminIp->hasAny(array('ipAddress' => $this->request->clientIp())))
            {
                $this->log('User login failed due to systems administration IP access failure.');
                $this->Session->setFlash('Username or password is incorrect.');
                AuditLog::log(AUDIT_IP_NOT_AUTHORIZED);
                $this->logout();
            }
            // Check to see if we require logins to configured devices.  If so, we can never get past here
            // without device identifiers.
            else if ((bool)Configure::read('Clips.agency.disableUnknownDeviceLogin') && 
                        !$this->CurrentDevice->stateId())
            {
                $this->log('User login failed due to not having required device identifiers.');
                $this->Session->setFlash('Username or password is incorrect.');
                AuditLog::log(AUDIT_LOGIN_NO_DEVICE_IDENTIFIERS);
                $this->logout();
            }
            // TODO: This is put here because Phoenix doesn't want the ability to log in, even if an administrator,
            //       without a TOC code.  This should be configurable to use the legacy behavior, or this behavior.
            else if ((bool)Configure::read("$interfaceConfigPath.requireStateUserId") == true
                && !$this->CurrentUser->stateUserId)
            {
                $this->log('User login failed due to not having required state user identifier.');
                $this->Session->setFlash('Username or password is incorrect.');
                AuditLog::log(AUDIT_LOGIN_NO_STATE_USER_ID);
                $this->logout();
            }
            // Check if the user is an agency administrator.
            else if ($this->CurrentUser->isAgencyAdmin())
            {
                // Nothing to really do here.
            }
            // At this point, the user must be able to run transactions
            else if (!$this->CurrentUser->hasPermission('runTransaction'))
            {
                $this->log('User login failed due to not having any assigned permissions.');
                $this->Session->setFlash('Username or password is incorrect.');
                AuditLog::log(AUDIT_PERMISSIONS_NOT_ASSIGNED);
                $this->logout();
            }
            // The device must be enabled
            else if (!$this->CurrentDevice->deviceEnabled)
            {
                $this->log('User login failed because the device is disabled.');
                $this->Session->setFlash('Username or password is incorrect.');
                AuditLog::log(AUDIT_LOGIN_DEVICE_DISABLED);
                $this->logout();
            }
            // They must also have device identifiers and the device must be enabled
            else if (!$this->CurrentDevice->stateId())
            {
                $this->log('User login failed due to not having required device identifiers.');
                $this->Session->setFlash('Username or password is incorrect.');
                AuditLog::log(AUDIT_LOGIN_NO_DEVICE_IDENTIFIERS);
                $this->logout();
            }
            
            // If the user gets this far, check to see if they're already logged in to another device and, if so,
            // kick them out of that device.
            $sm = ClassRegistry::init('UserSession');
            $otherSessions = $sm->find('all', array(
                'fields' => array('sessionId'),
                'conditions' => array(
                    'agencyUserId' => $this->CurrentUser->agencyUserId,
                    'sessionId !=' => $this->Session->id()
                )
            ));
            
            if (!empty($otherSessions))
            {
                foreach($otherSessions as $otherSession)
                {
                    $otherSession = $otherSession['UserSession'];
                    $otherSession['forceLogoff'] = 2;
                    $sm->set($otherSession);
                    $sm->save(null, false);
                }
            }
            
            // Redirect the user to somewhere more appropriate
            AuditLog::Log(AUDIT_LOGIN, $this->CurrentUser->userName, $this->CurrentDevice->name);
        }

        if ($this->CurrentUser->loggedIn())
        {
            $redirectUrl = $this->redirectUrlByPermission();
            if (!empty($redirectUrl))
            {
                $this->Session->delete('Reauthenticate');
                $this->Session->write('LastAuthcheck', time());
                return $this->redirect($redirectUrl);
            }
        }
        else
        {
            throw new MethodNotAllowedException();
        }

        return $this->redirect(array('action' => 'login'));
    }

    public function logout()
    {
        if (!empty($this->request->params['named']['reason']))
            $this->Session->setFlash($this->request->params['named']['reason']);

        // Preserve the flash information for the redirect.  This will allow us to display error messages when being
        // logged off.
        $flash = CakeSession::read('Message');

        // Cake doesn't destroy the session on logout (though I wish it did).  Destroy the session information.
        $this->Session->destroy();

        // Perform the logout and remember the location to redirect to.
        $redirect = array('action' => 'login');

        // Restore the flash message
        CakeSession::write('Message', $flash);

        // Do the redirect
        return $this->redirect($redirect);
    }

    public function change_password()
    {
        // HACK: Load agency specific authorization/config
        if ($this->CurrentAgency->impersonating())
        {
            App::uses('ClipsConfiguration', 'Lib');
            App::uses('ClipsAuthComponent', 'Controller/Component');
            $this->ActiveAuth = $this->Components->load('ClipsAuth');
            $agencyConfig = ClipsConfiguration::read($this->CurrentAgency->agencyId);

            $this->ActiveAuth->authenticate = $agencyConfig['config']['agency']['authenticate'];
            $this->ActiveAuth->authorize = $agencyConfig['config']['agency']['authorize'];

            $this->set('auth_types', $this->ActiveAuth->authenticate);
            $this->set('auth_policies', $this->ActiveAuth->policy());
            $auth = $this->ActiveAuth;
        }
        else
            $auth = $this->Auth;
        // END HACK: Load agency specific authorization/config

        if (!$auth->policy('enforce_password_policies'))
            return $this->redirect($this->referer());

        // Modify the validation table for the User.  This allows us to add some custom fields for change password
        // functionality.
        $this->User->validate = array(
                'currentPassword' => array(
                    'notBlank' => array(
                        'rule' => 'notBlank',
                        'message' => 'Current Password is a required field'
                    ),
                    // This rule validates the old password is correct
                    'exists' => array(
                        'rule' => array('matches', 'userPassword', array('agencyUserId' => $this->CurrentUser->agencyUserId)),
                        'message' => 'Incorrect password'
                    )
                ),
                'newPassword' => array(
                    'notBlank' => array(
                        'rule' => 'notBlank',
                        'message' => 'New Password is a required field'
                    ),
                    'complex' => array(
                        'rule' => 'complexity',
                        'message' => 'The password does not meet the required complexity requirements.'
                    ),
                    'equal' => array(
                        'rule' => array('fieldsEqual', 'confirmPassword'),
                        'message' => 'Passwords do not match'
                    )
                ),
                'confirmPassword' => array(
                    'notBlank' => array(
                        'rule' => 'notBlank',
                        'message' => 'Confirm Password is a required field.'
                    ),
                    'equal' => array(
                        'rule' => array('fieldsEqual', 'newPassword'),
                        'message' => 'Passwords do not match'
                ))
            );

        if ($this->request->is('get'))
            return;
        
        $data = $this->request->data['User'];

        $this->User->set($data);
        if ($this->User->validates())
        {
            // Initialize the user model.  Pass null to create to prevent loading default values
            $data = array(
                'agencyUserId' => $this->CurrentUser->agencyUserId,
                'userPassword' => $data['newPassword'],
                'forceExpire' => 0
            );
            $this->User->clear();
            if ($this->User->save($data, false))
            {
                AuditLog::log(AUDIT_PWD_CHANGE);

                CakeSession::delete('Auth.User.forceExpire');

                $this->Session->setFlash('Your password has been changed.  You will automatically be redirected shortly.');
                $this->Session->write('password_expired', false);
                
                $redirectUrl = $this->redirectUrlByPermission();
                if (!empty($redirectUrl))
                    $this->delayRedirect($redirectUrl, 3);
                else
                    return $this->redirect(array('action' => 'login'));

                return $this->redirect(array('action' => 'change_password'));
            }
            else
                $this->Session->setFlash('Failed to save the new password.');
        }
    }

    public function admin_index()
    {
        // HACK: Load agency specific authorization/config
        if ($this->CurrentAgency->impersonating())
        {
            App::uses('ClipsConfiguration', 'Lib');
            App::uses('ClipsAuthComponent', 'Controller/Component');
            $this->ActiveAuth = $this->Components->load('ClipsAuth');
            $agencyConfig = ClipsConfiguration::read($this->CurrentAgency->agencyId);

            $this->ActiveAuth->authenticate = $agencyConfig['config']['agency']['authenticate'];
            $this->ActiveAuth->authorize = $agencyConfig['config']['agency']['authorize'];

            $this->set('auth_types', $this->ActiveAuth->authenticate);
            $this->set('auth_policies', $this->ActiveAuth->policy());
            $auth = $this->ActiveAuth;
        }
        else
            $auth = $this->Auth;
        // END HACK: Load agency specific authorization/config

        // Get a list of users.
        $this->set('users', $this->Filter->filter(array(
                'contain' => array('Agency.interface', 'Group'),
                'conditions' => array('User.agencyId' => $this->CurrentAgency->agencyId),
                'order' => array('userName' => 'asc'),
                'paginate' => true,
                'limit' => 25
            )));
    }

    public function system_index()
    {
        // Get a list of users.
        $this->set('users', $this->Filter->filter(array(
                'contain' => array('Agency.interface', 'Group'),
                'conditions' => array('User.agencyId' => null, 'User.isPowerUser' => 1),
                'order' => array('userName' => 'asc'),
                'paginate' => true,
                'limit' => 25
            )));
    }

    public function admin_add()
    {
        // HACK: Load agency specific authorization/config
        if ($this->CurrentAgency->impersonating())
        {
            App::uses('ClipsConfiguration', 'Lib');
            App::uses('ClipsAuthComponent', 'Controller/Component');
            $this->ActiveAuth = $this->Components->load('ClipsAuth');
            $agencyConfig = ClipsConfiguration::read($this->CurrentAgency->agencyId);

            $this->ActiveAuth->authenticate = $agencyConfig['config']['agency']['authenticate'];
            $this->ActiveAuth->authorize = $agencyConfig['config']['agency']['authorize'];

            $this->set('auth_types', $this->ActiveAuth->authenticate);
            $this->set('auth_policies', $this->ActiveAuth->policy());
            $auth = $this->ActiveAuth;
        }
        else
            $auth = $this->Auth;
        // END HACK: Load agency specific authorization/config
        
        if (!$this->request->is('post'))
            return;

        // Remove the password if we're not using SQL authentication
        if (!$auth->policy('enforce_password_policies'))
        {
            // Remove the user password validation since we don't care anymore.
            unset($this->User->validate['userPassword']);
            unset($this->User->validate['confirmPassword']);
            // Put a bogus user password data since we won't be using it anyway.
            $this->request->data['User']['userPassword'] =
                $this->request->data['User']['confirmPassword'] = 'placeholder';
        }

        foreach($this->request->data['User'] as $key => $value)
            $this->request->data['User'][$key] = trim($value);
        
        $this->User->clear();
        $data = array_merge(
            $this->request->data['User'], array('agencyId' => $this->CurrentAgency->agencyId)
        );
        //if ($this->twoFactor())
        //    $data['forceExpire'] = false;
        $success = $this->User->saveUser($data);
        if (!$success)
        {
            $this->Session->setFlash('Failed to create the new user account.');
            return;
        }

        AuditLog::Log(AUDIT_USER_ADDED, $this->request->data['User']['userName']);

        $this->Session->setFlash('The new user has been created.');
        return $this->redirect(array('action' => 'index'));
    }

    public function system_add()
    {
        if (!$this->request->is('post'))
            return;
        
        unset($this->request->data['User']['agencyId']);
        foreach($this->request->data['User'] as $key => $value)
            $this->request->data['User'][$key] = trim($value);

        $this->User->clear();
        if (!$this->User->saveUser($this->request->data))
        {
            $this->Session->setFlash('Failed to create the new administrator account.');
            return;
        }

        AuditLog::Log(AUDIT_SYSTEM_ADMIN_ADDED, $this->request->data['User']['userName']);
        $this->Session->setFlash('The new administrator account has been created.');
        return $this->redirect(array('action' => 'index'));
    }

    public function admin_edit($id)
    {
        $this->User->id = $id;
        // HACK: Load agency specific authorization/config
        if ($this->CurrentAgency->impersonating())
        {
            App::uses('ClipsConfiguration', 'Lib');
            App::uses('ClipsAuthComponent', 'Controller/Component');
            $this->ActiveAuth = $this->Components->load('ClipsAuth');
            $agencyConfig = ClipsConfiguration::read($this->CurrentAgency->agencyId);

            $this->ActiveAuth->authenticate = $agencyConfig['config']['agency']['authenticate'];
            $this->ActiveAuth->authorize = $agencyConfig['config']['agency']['authorize'];

            $this->set('auth_types', $this->ActiveAuth->authenticate);
            $this->set('auth_policies', $this->ActiveAuth->policy());
            $auth = $this->ActiveAuth;
        }
        else
            $auth = $this->Auth;
        // END HACK: Load agency specific authorization/config

        if ($this->request->is('get'))
        {
            // Read all fields except userPassword and confirmPassword.
            $fields = array_diff(
                array_keys($this->User->schema()), array('userPassword', 'confirmPassword'));

            $this->request->data = $this->User->read($fields);
            return;
        }

        // Remove the password if we're not using SQL authentication
        if (!$auth->policy('enforce_password_policies'))
        {
            // Remove the user password validation since we don't care anymore.
            unset($this->User->validate['userPassword']);
            unset($this->User->validate['confirmPassword']);
            // Remove the user password data since we won't be saving it.
            unset($this->request->data['User']['userPassword']);
            unset($this->request->data['User']['confirmPassword']);
        }

        foreach($this->request->data['User'] as $key => $value)
            $this->request->data['User'][$key] = trim($value);
        
        $success = $this->User->saveUser(array_merge(
                $this->request->data['User'], array('agencyId' => $this->CurrentAgency->agencyId)
            ));
        if (!$success)
        {
            $this->Session->setFlash('Failed to update the user.');
            return;
        }

        if (!empty($this->request->data['User']['userPassword']))
            AuditLog::log(AUDIT_PWD_CHANGE_ADMIN, $this->request->data['User']['userName']);

        AuditLog::Log(AUDIT_USER_EDITED, $this->request->data['User']['userName']);

        $this->Session->setFlash('The user has been updated.');
        return $this->redirect(array('action' => 'index'));
    }

    public function system_edit($id)
    {
        $this->User->id = $id;
        if ($this->request->is('get'))
        {
            // Read all fields except userPassword and confirmPassword.
            $fields = array_diff(
                array_keys($this->User->schema()), array('userPassword', 'confirmPassword'));

            $this->request->data = $this->User->read($fields);
            return;
        }

        foreach($this->request->data['User'] as $key => $value)
            $this->request->data['User'][$key] = trim($value);
        
        if (!$this->User->saveUser($this->request->data))
        {
            $this->Session->setFlash('Failed to update the administrator.');
            return;
        }

        AuditLog::Log(AUDIT_SYSTEM_ADMIN_EDITED, $this->request->data['User']['userName']);

        $this->Session->setFlash('The administrator has been updated.');
        return $this->redirect(array('action' => 'index'));
    }

    public function admin_enable($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        if (!$this->User->enable($id))
            $this->Session->setFlash('Failed to enable the user.');
        else
        {
            $this->User->id = $id;
            $this->User->read();
            AuditLog::Log(AUDIT_USER_ENABLED, $this->User->data['User']['userName']);
            $this->Session->setFlash('The user has been enabled.');
        }

        return $this->redirect(array('action' => 'index'));
    }

    public function system_enable($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        if (!$this->User->enable($id))
            $this->Session->setFlash('Failed to enable the user.');
        else
        {
            $this->User->id = $id;
            $this->User->read();
            AuditLog::Log(AUDIT_SYSTEM_ADMIN_ENABLED, $this->User->data['User']['userName']);
            $this->Session->setFlash('The user has been enabled.');
        }

        return $this->redirect(array('action' => 'index'));
    }

    public function admin_disable($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        if (!$this->User->disable($id))
            $this->Session->setFlash('Failed to disable the user.');
        else
        {
            $this->User->id = $id;
            $this->User->read();
            AuditLog::Log(AUDIT_USER_DISABLED, $this->User->data['User']['userName']);
            $this->Session->setFlash('The user has been disabled.');
        }

        return $this->redirect(array('action' => 'index'));
    }

    public function system_disable($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        if (!$this->User->disable($id))
            $this->Session->setFlash('Failed to disable the user.');
        else
        {
            $this->User->id = $id;
            $this->User->read();
            AuditLog::Log(AUDIT_SYSTEM_ADMIN_DISABLED, $this->User->data['User']['userName']);
            $this->Session->setFlash('The user has been disabled.');
        }

        return $this->redirect(array('action' => 'index'));
    }

    public function admin_delete($id)
    {
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();

        $this->User->id = $id;
        $this->User->read();
        $data = $this->User->data;

        if (!$this->User->deleteUser($id))
            $this->Session->setFlash('Failed to delete the user.');
        else
        {
            $this->Session->setFlash('The user has been deleted.');
            AuditLog::Log(AUDIT_USER_DELETED, $data['User']['userName']);
        }

        return $this->redirect(array('action' => 'index'));
    }

    public function system_delete($id)
    {
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();

        $this->User->id = $id;
        $this->User->read();
        $data = $this->User->data;
        
        if (!$this->User->deleteUser($id))
            $this->Session->setFlash('Failed to delete the administrator.');
        else
        {
            $this->Session->setFlash('The administrator has been deleted.');
            AuditLog::Log(AUDIT_SYSTEM_ADMIN_DELETED, $data['User']['userName']);
        }

        return $this->redirect(array('action' => 'index'));
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.
    public function admin_assignments($id = null)
    {
        if ($id != null)
            $this->request->data['User']['agencyUserId'] = $id;

        if (!empty($this->request->data['User']['agencyUserId']))
        {
            // Get the assigned permissions
            $data = $this->User->find('first', array(
                'fields' => array('agencyUserId', 'agencyId'),
                'contain' => array(
                    'Group' => array(
                        'fields' => array('agencyGroupId', 'groupName')
                    ),
                ),
                'conditions' => array(
                    $this->User->primaryKey => $this->request->data['User']['agencyUserId']
                )
                ));
            $assigned = Set::combine($data, 'Group.{n}.agencyGroupId', 'Group.{n}.groupName');

            // Get the unassigned permissions
            $permissions = $this->User->Group->find('list', array(
                'conditions' => array(
                    'agencyId' => $data['User']['agencyId']
                )
                ));
            $unassigned = array_diff($permissions, $assigned);

            $this->set('assigned', $assigned);
            $this->set('unassigned', $unassigned);
        }

        $users = $this->User->find('all', array(
            'fields' => array('agencyUserId', 'userFullNameLast', 'userName'),
            'order' => 'userFullNameLast',
            'conditions' => array(
                'agencyId' => $this->CurrentAgency->agencyId
            )
        ));

        $users = Set::combine($users, '{n}.User.agencyUserId', array('{0} ({1})', '{n}.User.userFullNameLast', '{n}.User.userName'));
        $this->set('Users', $users);
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.  Maybe alter the UI
    //       so that it uses checkboxes?
    public function admin_assign()
    {
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();

        if (($this->request->data['User']['assignAction'] == 'assign' && !empty($this->request->data['Group'][0]['agencyGroupId'])) ||
            ($this->request->data['User']['assignAction'] == 'unassign' && !empty($this->request->data['Group'][1]['agencyGroupId'])))
        {
            $this->User->contain(array('Group.agencyGroupId'));
            $data = $this->User->find('first', array(
                'fields' => array('agencyUserId'),
                'contain' => array('Group.agencyGroupId'),
                'conditions' => array(
                    'agencyUserId' => $this->request->data['User']['agencyUserId']
                )
                ));

            $count = count($data['Group']);
            for ($a = 0; $a < $count; ++$a)
                unset($data['Group'][$a]['UserPermission']);

            if ($this->request->data['User']['assignAction'] == 'unassign')
            {
                $toRemove = $this->request->data['Group'][1]['agencyGroupId'];
                foreach ($toRemove as $id)
                {
                    $a = 0;
                    $count = count($data['Group']);
                    while ($a < $count)
                    {
                        if ($data['Group'][$a]['agencyGroupId'] == $id)
                        {
                            --$count;
                            array_splice($data['Group'], $a, 1);
                            continue;
                        }
                        ++$a;
                    }
                }

                // If the array is empty, Cake uses a special placeholder indicating that all records should be deleted.
                if (empty($data['Group']))
                    $data['Group'][]['agencyGroupId'] = null;
            }
            else
            {
                $toAdd = $this->request->data['Group'][0]['agencyGroupId'];
                foreach ($toAdd as $id)
                    $data['Group'][]['agencyGroupId'] = $id;
            }

            if ($this->User->save($data))
            {
                $this->User->id = $this->request->data['User']['agencyUserId'];
                $this->User->read();
                AuditLog::Log(AUDIT_USER_PERMISSIONS, $this->User->data['User']['userName']);
                $this->Session->setFlash('The user assignments have been updated.');
            }
            else
                $this->Session->setFlash('Failed to save the user assignments.');
        }

        return $this->redirect(array('action' => 'assignments', $this->request->data['User']['agencyUserId']));
    }
    
    private function defaultAgency($agencyId = null)
    {
        // Load the user's previously selected agency id and set it as the default selection in the agency drop down.
        // TODO: Should this really be a controller action thing?  It makes more sense in the view, I think.
        $this->Cookie = $this->Components->load('Cookie');
        $this->Cookie->name = 'ClipsLoginAgency';
        $this->Cookie->time = '2 Days';
        //$this->Cookie->path = '/users/login';

        if ($agencyId !== null)
            $this->Cookie->write('agencyId', $agencyId, false, $this->Cookie->time);

        return $this->Cookie->read('agencyId');
    }

    public function test_two_factor()
    {
        echo json_encode(array('error' => false, 'responsevalid' => true));
        exit;
    }

    private function redirectUrlByPermission()
    {
        if (!$this->CurrentUser->isSystemAdmin())
        {
            // If the user can run transactions and is on a device with state identifiers, redirect them to the 
            // responses tab.
            if ($this->CurrentUser->hasPermission('runTransaction') 
                    && $this->CurrentDevice->deviceEnabled
                    && $this->CurrentDevice->stateid()) {
                return array('controller' => 'responses', 'action' => 'index');
            }
        }

        // Mobile users can't access any history or administrative screens at this time.
        // return nothing useful.
        if ($this->Mobile->isMobile())
        {
            $this->Session->setFlash('You cannot login to a mobile device as an administrator.');
            return false;
        }

        if ($this->CurrentUser->isAgencyAdmin() == true) {
            return array('controller' => 'administrations', 'action' => 'index', 'admin' => true);
        }

        return array('controller' => 'archive_responses', 'action' => 'index', 'admin' => false);
    }

    /*
    private function twoFactor()
    {
        return true;
        // Check to see if TwoFactor is even enabled in the CLIPS configuration.
        $conf = Configure::read('Clips.agency.twoFactor');
        if (empty($conf['authenticator']) || $conf['authenticator'] == 'none')
        {
            // Force a failure if two-factor isn't configured and we're attempting to login from a mobile device.
            if ($this->Mobile->isMobile())
            {
                CakeLog::write('error', 'Login failed because of an attempt to login from a mobile device ' .
                    'without a configured two-factor authentication scheme.');
                return false;
            }

            // It's not enabled, so claim it worked
            return true;
        }

        // Require Two-Factor if:
        //
        // 1) The device is Mobile
        // 2) The device has two-factor enabled
        // 3) A System administrator is logging in and Two-Factor is required for System Admins.
        // 4) An Agency administrator is logging in and Two-Factor is required for Agency Admins.
        $twoFactorRequired =
            $this->Mobile->isMobile()
            // TODO: Change twoFactor to a drop down selection to pick from available authenticators.
            || $this->CurrentDevice->twoFactor == true;
        //|| ($conf['requiredForSystemAdmin'] == true && $this->CurrentUser->isSystemAdmin())
        //|| ($conf['requiredForAgencyAdmin'] == true && $this->CurrentUser->isAgencyAdmin());

        if ($twoFactorRequired == false)
            return true;

        // Read the authenticator settings
        $authenticatorName = $conf['authenticator'];

        $settings = array();
        if (!empty($conf['authenticatorSettings'][$authenticatorName]))
            $settings = $conf['authenticatorSettings'][$authenticatorName];

        // Load and create an instance of the authenticator
        App::uses($authenticatorName, 'Config/TwoFactor');

        $authenticator = new $authenticatorName($settings);
        return $authenticator->authenticate(@$this->request->data['User']['token']);

        // TODO: Update the user login attempts
    }
    */
}
