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
App::uses('AppController', 'Controller');

class AgencyCodesController 
	extends AppController
{
    public function admin_index()
    {
        $this->paginate = array(
            'contain'    => array('AgencyCodeType.agencyId', 'AgencyCodeType.name'),
            'conditions' => array(
                'AgencyCodeType.agencyId' => $this->CurrentAgency->agencyId,
                'AgencyCodeType.name' => '$_ORI_DD_$'
            ),
            'order'      => array('AgencyCode.code' => 'asc'),
            'limit'      => 25
        );

        $this->set('agency_codes', $this->paginate('AgencyCode'));
    }

    public function admin_add()
    {
        if (!$this->request->is('post'))
            return;

        $agencyCodeTypeId = $this->AgencyCode->AgencyCodeType->field(
            $this->AgencyCode->AgencyCodeType->primaryKey,
            array(
                'agencyId' => $this->CurrentAgency->agencyId,
                'name'     => '$_ORI_DD_$'
            ));

        foreach($this->request->data[$this->AgencyCode->alias] as $key => $value)
            $this->request->data[$this->AgencyCode->alias][$key] = trim($value);
        
        $data = array_merge(
            $this->request->data[$this->AgencyCode->alias],
            array('agencyCodeTypeId' => $agencyCodeTypeId)
        );
        $this->AgencyCode->clear();
        if (!$this->AgencyCode->save($data))
        {
            $this->Session->setFlash('Failed to add the new ORI.');
            return;
        }
        
        AuditLog::Log(AUDIT_ORI_ADDED, $this->request->data['AgencyCode']['code']);

        $this->Session->setFlash('The ORI has been added.');
        return $this->redirect(array('action' => 'index'));
    }
    
    public function admin_edit($id)
    {
        $this->AgencyCode->id = $id;
        if ($this->request->is('get'))
        {
            $this->request->data = $this->AgencyCode->read();
            return;
        }
        
        foreach($this->request->data[$this->AgencyCode->alias] as $key => $value)
            $this->request->data[$this->AgencyCode->alias][$key] = trim($value);
        
        if (!$this->AgencyCode->save($this->request->data))
        {
            $this->Session->setFlash('Failed to update the ORI.');
            return;
        }

        AuditLog::Log(AUDIT_ORI_CHANGED, $this->request->data['AgencyCode']['code']);

        $this->Session->setFlash('The ORI has been updated.');
        return $this->redirect(array('action' => 'index'));
    }
    
    public function admin_delete($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        $this->AgencyCode->id = $id;
        $ori = $this->AgencyCode->field('code');
        
        if (!$this->AgencyCode->delete())
            $this->Session->setFlash('Failed to delete the ORI.');
        else
        {
            AuditLog::Log(AUDIT_ORI_DELETED, $ori);
            $this->Session->setFlash('The ORI has been deleted.');
        }

        return $this->redirect(array('action' => 'index'));
    }
}
