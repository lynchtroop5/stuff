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

class AdminIpsController extends AppController
{
    public function system_index()
    {
        // Get a list of ips.
        $this->paginate = array(
            'order'      => array('ipAddress' => 'asc'),
            'limit'      => 25
        );

        $this->set('ips', $this->paginate('AdminIp'));
    }

    public function system_add()
    {
        if (!$this->request->is('post'))
            return;

        foreach($this->request->data['AdminIp'] as $key => $value)
            $this->request->data['AdminIp'][$key] = trim($value);
        
        $this->AdminIp->clear();
        if (!$this->AdminIp->save($this->request->data))
        {
            $this->Session->setFlash('Failed to create the new IP Address.');
            return;
        }
        
        AuditLog::Log(AUDIT_IP_ADD, $this->request->data['AdminIp']['ipAddress']);
        
        $this->Session->setFlash('The new IP Address has been created.');
        return $this->redirect(array('action' => 'index'));
    }

    public function system_edit($id)
    {
        $this->AdminIp->id = $id;
        if ($this->request->is('get'))
        {
            $this->request->data = $this->AdminIp->read();
            return;
        }
        
        foreach($this->request->data['AdminIp'] as $key => $value)
            $this->request->data['AdminIp'][$key] = trim($value);
        
        $oldIp = $this->AdminIp->field('ipAddress');
        if ($this->AdminIp->save($this->request->data))
        {
            AuditLog::Log(AUDIT_IP_CHANGED, $oldIp, $this->request->data['AdminIp']['ipAddress']);

            $this->Session->setFlash('The IP Address has been updated.');
            return $this->redirect(array('action' => 'index'));
        }
        else
            $this->Session->setFlash('Failed to update the IP Address.');
    }
    
    public function system_delete($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        $this->AdminIp->id = $id;
        $oldIp = $this->AdminIp->field('ipAddress');
        
        if (!$this->AdminIp->delete())
            $this->Session->setFlash('Failed to delete the IP Address');
        else
        {
            AuditLog::Log(AUDIT_IP_DELETE, $oldIp);
            $this->Session->setFlash('The IP Address has been deleted.');
        }

        return $this->redirect(array('action' => 'index'));
    }
}
