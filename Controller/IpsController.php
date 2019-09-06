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

class IpsController extends AppController
{
    public function system_add($agencyId=null)
    {
        if (empty($agencyId))
            throw new HttpInvalidParamException ();

        $this->set('agencyId', $agencyId);
        if (!$this->request->is('post'))
            return;

        foreach($this->request->data['Ip'] as $key => $value)
            $this->request->data['Ip'][$key] = trim($value);
        
        $this->Ip->clear();
        $success = $this->Ip->save(array_merge(
            $this->request->data['Ip'],
            array('agencyId' => $agencyId)
        ));
        if (!$success)
        {
            $this->Session->setFlash('Failed to add the new IP Address.');
            return;
        }

        $this->Ip->Agency->id = $agencyId;
        $agencyName = $this->Ip->Agency->field('agencyName');
        AuditLog::Log(AUDIT_AGENCY_IP_ADD, 
            $this->request->data['Ip']['ipAddress'] . '/' . $this->request->data['Ip']['ipMask'], 
            $agencyName);

        $this->Session->setFlash('The new IP Address has been added.');
        return $this->redirect(array('controller' => 'agencies', 'action' => 'edit', $agencyId));
    }

    public function system_edit($id)
    {
        $this->Ip->id = $id;

        $agencyId = $this->Ip->field('agencyId');
        $this->set('agencyId', $agencyId);

        if ($this->request->is('get'))
        {
            $this->request->data = $this->Ip->read();
            return;
        }

        foreach($this->request->data['Ip'] as $key => $value)
            $this->request->data['Ip'][$key] = trim($value);
        
        $oldIpAndMask = $this->Ip->field('ipAndMask');
        if (!$this->Ip->save($this->request->data))
        {
            $this->Session->setFlash('Failed to update the IP Address.');
            return;
        }

        $this->Ip->Agency->id = $agencyId;
        $agencyName = $this->Ip->Agency->field('agencyName');
        AuditLog::Log(AUDIT_AGENCY_IP_CHANGED, $oldIpAndMask,
            $this->request->data['Ip']['ipAddress'] . '/' . $this->request->data['Ip']['ipMask'], 
            $agencyName);
        
        $this->Session->setFlash('The IP Address has been updated.');
        return $this->redirect(array('controller' => 'agencies', 'action' => 'edit', $agencyId));
    }
    
    public function system_delete($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        $this->Ip->id = $id;
        $agencyId = $this->Ip->field('agencyId');
        $oldIpAndMask = $this->Ip->field('ipAndMask');

        if (!$this->Ip->delete())
            $this->Session->setFlash('Failed to delete the IP Address.');
        else
        {
            $this->Ip->Agency->id = $agencyId;
            $agencyName = $this->Ip->Agency->field('agencyName');
            AuditLog::Log(AUDIT_AGENCY_IP_DELETE, $oldIpAndMask, $agencyName);

            $this->Session->setFlash('The IP Address has been deleted.');
        }

        return $this->redirect(array('controller' => 'agencies', 'action' => 'edit', $agencyId));
    }
}
