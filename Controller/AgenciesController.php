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

class AgenciesController extends AppController
{
    public $components = array('Filter' => array(
        'datetime' => null,
        'fields' => array('agencyEnabled')
    ));
    
    public function beforeFilter()
    {
        if (!$this->Filter->Check('agencyEnabled'))
            $this->Filter->Write('agencyEnabled', true);
        
        parent::beforeFilter();
    }
    
    // TODO: This should be in the AdministrationsController.
    public function agency_list()
    {
        $agencies = $this->Agency->find('list', array(
                'conditions' => array('agencyEnabled' => 1)
            )); 
        if ($this->request->is('requested'))
            return $agencies;

        $this->set('agencies', $agencies);
    }

    public function system_index()
    {
        $totalAgencies = $this->Agency->find('count', array(
            'conditions' => array('Agency.agencyEnabled' => 1)
        ));
        $this->set('total_agencies', $totalAgencies);
        
        $agencies = $this->Filter->filter(array(
            'order'      => array('name' => 'asc'),
            'paginate'   => true,
            'limit'      => 25
        ));

        // Get a list of agencies.
        $this->set('agencies', $agencies);
    }

    public function system_add()
    {
        $totalAgencies = $this->Agency->find('count', array(
            'conditions' => array('Agency.agencyEnabled' => 1)
        ));
        if ($totalAgencies >= Licensing::MaxAgencies())
            return $this->redirect($this->referer('/admin/agencies'));

        if (!$this->request->is('post'))
            return;

        foreach($this->request->data['Agency'] as $key => $value)
            $this->request->data['Agency'][$key] = trim($value);

        $this->Agency->clear();
        if (!$this->Agency->createAgency($this->request->data))
        {
            $this->Session->setFlash('Failed to create the new agency.');
            return;
        }
            
        AuditLog::Log(AUDIT_AGENCY_ADDED, $this->request->data['Agency']['agencyName']);
            
        $this->Session->setFlash('The new agency has been created.');
        return $this->redirect(array('action' => 'index'));
     }
    
    public function system_edit($id)
    {
        $this->Agency->id = $id;
        if ($this->request->is('get'))
        {
            $this->Agency->contain(array('Ip' => array('order' => 'ipAddress')));
            $this->request->data = $this->Agency->read();
            return;
        }

        foreach($this->request->data['Agency'] as $key => $value)
            $this->request->data['Agency'][$key] = trim($value);
        
        if (!$this->Agency->save($this->request->data))
        {
            $this->Session->setFlash('Failed to update the agency.');
            return;
        }

        AuditLog::Log(AUDIT_AGENCY_CHANGE, $this->request->data['Agency']['agencyName']);

        $this->Session->setFlash('The agency has been updated.');
        return $this->redirect(array('action' => 'index'));
    }
    
    public function system_enable($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        if (!$this->Agency->enable($id))
            $this->Session->setFlash('Failed to enabled the agency.');
        else
        {
            $this->Agency->id = $id;
            $agency = $this->Agency->field('agencyName');
            AuditLog::Log(AUDIT_ENABLE_AGENCY, $agency);
            $this->Session->setFlash('The agency has been enabled.');
        }

        return $this->redirect(array('action' => 'index'));
    }
    
    public function system_disable($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        if (!$this->Agency->disable($id))
            $this->Session->setFlash('Failed to disable the agency.');
        else
        {
            $this->Agency->id = $id;
            $agency = $this->Agency->field('agencyName');
            AuditLog::Log(AUDIT_DISABLE_AGENCY, $agency);

            $this->Session->setFlash('The agency has been disabled.');
        }

        return $this->redirect(array('action' => 'index'));
    }

    public function system_impersonate()
    {
        if ($this->request->is('post'))
            $this->CurrentAgency->impersonate($this->request->data['Agency']['agencyId']);

        // If the user has selected an agency id, redirect back to their original page.
        if ($this->CurrentAgency->agencyId)
            return $this->redirect($this->request->referer(), null, true);

        // If no agency was selected, redirect back to the core administrator page.
        return $this->redirect(array('controller' => 'administrations', 'action' => 'index', 'admin' => 'true'));
    }
}
