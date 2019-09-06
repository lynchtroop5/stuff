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

class UserSessionsController
	extends AppController
{
    public function admin_index()
    {
        $this->paginate = array(
            'contain'    => array(
                'User' => array(
                    'fields' => array('agencyUserId', 'userName', 'userLastName', 'userFirstName')
                ),
                'Device' => array(
                    'fields' => array('agencyDeviceId', 'name', 'computerName')
                )
            ),
            'fields'     => array('UserSession.sessionId','UserSession.ipAddress', 'UserSession.location', 
                                  'UserSession.forceLogoff', 'UserSession.lastActivityTime', 'UserSession.lastTransaction'),
            'conditions' => array(
                'UserSession.agencyId' => $this->CurrentAgency->agencyId,
                'UserSession.agencyUserId is not null',
                'UserSession.forceLogoff' => 0
            ),
            'order'      => array('UserSession.lastActivityTime' => 'asc'),
            'limit'      => 25
        );

        $this->set('user_sessions', $this->paginate('UserSession'));
	}
    
    public function admin_logoff($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        $this->UserSession->id = $id;
        if ($this->UserSession->field('agencyUserId') == $this->CurrentUser->agencyUserId)
        {
            $this->Session->setFlash('You cannot log yourself out form this page.');
            return $this->redirect(array('action' => 'index'));
        }
        
        $success = $this->UserSession->save(array(
            'sessionId' => $id,
            'forceLogoff' => true
        ));
        
        $this->Session->setFlash('The user has been logged off.');
        return $this->redirect(array('action' => 'index'));
    }

    public function admin_disable_user($id, $uid)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();
        
        $user = ClassRegistry::init('User');
        $user->disable($uid);

        $this->UserSession->id = $id;
        $this->UserSession->saveField('forceLogoff', 1);

        $this->Session->setFlash('The user has been logged out and their account disabled.');
        return $this->redirect(array('action' => 'index'));
    }

    public function admin_disable_device($id, $did)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();
        
        $device = ClassRegistry::init('Device');
        $device->disable($did);

        $this->UserSession->id = $id;
        $this->UserSession->saveField('forceLogoff', 1);

        $this->Session->setFlash('The device has been disabled and the user logged out.');
        return $this->redirect(array('action' => 'index'));
    }

}
