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
App::uses('BaseAuthorize', 'Controller/Component/Auth');
App::uses('CurrentUser', 'Model/Datasource');

class ClipsAuthorize extends BaseAuthorize
{    
	public $permissionList = null;
	public $permissionTypes = null;

	public $actionMap = array(
                'agency_codes'   => 'configureSite',
                'audits'         => 'viewSecurityLog',
                'configurations' => 'configureSite',
                'devices'        => 'manageDevice',
                'form_groups'    => 'manageFormGroup',
                'groups'         => 'managePermissionGroup',
                'user_sessions'  => 'manageUser',
                'users'          => 'manageUser'
            );
	
	// Verify that a user has access to a particular controller/action by their permissions.  This will only be called
	// if the authorization is required, which it is not on login/logoff actions.
	public function authorize($user, CakeRequest $request)	
	{
		// The User must be logged in
            if (!CurrentUser::loggedIn())
                return false;
        
            // If the user is being forced to log off, do so.
            $forceOut = ClipsSession::instance()->forceLogoff();
            if ($forceOut > 0) 
            {
                switch($forceOut)
                {
                case 1: 
                    // We don't have access to the Session component, so we have to do what it does to write to the session
                    $this->_Controller->Session->setFlash('You have been logged out by an administrator');
                    AuditLog::Log(AUDIT_FORCE_LOGOUT);
                    break;
            
                case 2:
                    AuditLog::Log(AUDIT_FORCE_LOGOUT_DUPLICATE);
                    $this->_Controller->Session->setFlash('You have been logged out because you logged in to another device');
                    break;
            
                // TODO: There's currently nothing detecting this case and setting the logoff flag accordingly.
                case 3:
                    AuditLog::Log(AUDIT_FORCE_LOGOUT_DEVICE);
                    $this->_Controller->Session->setFlash('You have been logged because another user logged into your device');
                    break;
                }

                return $this->_Controller->redirect(array('controller' => 'users', 'action' => 'logout', 'admin' => false), null, true);
            }

            // Check if there was an error reading the user session
            $sessionError = ClipsSession::instance()->error();
            if ($sessionError != 0)
            {
                $sm = ClassRegistry::init('UserSession');
                $columns = array('lastActivityTime', 'agencyId', 'agencyUserId', 'agencyDeviceId', 'ipAddress', 'forceLogoff', 
                                 'location', 'sessionData', 'lastTransaction');
                $contain = array(
                    'Device' => array(
                        'fields' => array('deviceAlias', 'computerName', 'name', 'mnemonic', 'ori')
                    )
                    ,
                    'User' => array(
                        'fields' => array('userName', 'userFirstName', 'userLastName', 'stateUserId')
                    )
                );

                // Read the current session data
                $current = $sm->find('first', array(
                    'fields'     => $columns,
                    'contain'    => $contain,
                    'conditions' => array($sm->primaryKey => CakeSession::id()))
                );

                // Find what we think the last Session ID was
                $last = $sm->find('first', array(
                    'fields'     => $columns,
                    'contain'    => $contain,
                    'conditions' => array('ipAddress' => $request->clientIp()),
                    'order'      => 'lastActivityTime DESC'
                ));

                ob_start();
                print_r(array(
                    'Current' => $current,
                    'Previous' => $last
                ));
                $data = ob_get_contents();
                ob_end_clean();

                CakeLog::debug('Session/IP Conflict: ' . $data);

                $reason = ClipsSession::instance()->errorString($sessionError);
                AuditLog::Log(AUDIT_SESSION_ERROR, $reason);

                $this->_Controller->Session->setFlash($reason);
                return $this->_Controller->redirect(array(
                    'controller' => 'users', 
                    'action' => 'logout', 
                    'admin' => false), null, true);
            }

            // Set users forceExpire value for validation.
            $forceExpire = $this->_Controller->CurrentUser->forceExpire;
            // Check if change_password key from clips_policies is set to false and if forceExpire is true 
            // If conditions are met then write forceExpire = 0 to the session so that 
            // users will not be required to change their password.
            if (!$this->_Controller->viewVars['auth_policies']['change_password'] && $forceExpire) {
                CakeSession::write('Auth.User.forceExpire', 0);
            }

            // If we're using SQL authentication and the user account indicates that the password has been forced to 
            // expire, redirect to the change_password screen.
            if ($this->_Controller->Auth->policy('enforce_password_policies') &&
                $forceExpire && $this->_Controller->request->params['action'] != 'change_password')
            {
                AuditLog::Log(AUDIT_PWD_EXPIRE);

                $this->_Controller->Session->setFlash('Your password has expired and must be changed.');
                $this->_Controller->Session->write('password_expired', true);
                
                return $this->_Controller->redirect(array(
                    'controller' => 'Users',
                    'action' => 'change_password',
                    'admin' => null,
                    'system' => null), null, true);
            }

            // Check if the mobile interface is requiring a user re-authentication
            if ($this->_Controller->Mobile->isHandheld() &&
                $this->_Controller->request->params['action'] != 'do_login')
            {
                if (!$this->_Controller->Session->read('LastAuthcheck'))
                    $this->_Controller->Session->write('LastAuthcheck', time());
                $lastAuthCheck = $this->_Controller->Session->read('LastAuthcheck');
                if (time() - $lastAuthCheck >= 1 * 60 * 60) 
                {
                    $this->_Controller->Session->setFlash('You must re-authenticate your login credentials.');
                    $this->_Controller->Session->write('Reauthenticate', true);

                    if ($this->_Controller->request->params['action'] != 'login')
                    {
                        return $this->_Controller->redirect(array(
                                'controller' => 'users',
                                'action' => 'login'
                            ));
                    }
                }
            }

		// If we're performing a systems administrator action, verify that the user has system administrator access.
            if (isset($request->params['system']))
                return CurrentUser::isSystemAdmin();

		// If we're performing an administrative action, verify that we have permission
            if (isset($request->params['admin']))
            {
                $controller = strtolower($request->params['controller']);
                $action = strtolower($request->params['action']);

			// If we're in a controller defined by action_map, we need the permission specified.
                if (isset($this->actionMap[$controller]))
                    return CurrentUser::hasPermission($this->actionMap[$controller]);

                // If we're in the administrators controller, we need at least one administrative permission.
                if ($controller == 'administrations')
                    return CurrentUser::hasPermission(array_values($this->actionMap));

                // Last ditch effort to save your authorization
                if (CurrentUser::isSystemAdmin())
                        return true;

                // Don't know what you tried to access, but we're not going to allow it.
                return false;
            }
        
            // Otherwise, you're okay to access the action.  This should hit for responses, forms, request history, response
            // history, criminal history, and other user specific pages.
            return true;
	}
}
