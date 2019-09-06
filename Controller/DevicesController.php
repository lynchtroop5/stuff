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
class DevicesController extends AppController
{
    public $components = array('Filter' => array(
        'datetime' => null,
        'fields' => array(
            'name' => array('comparison' => 'like'),
            'computerName' => array('comparison' => 'like'),
            'ori' => array('comparison' => 'like'),
            'mnemonic' => array('comparison' => 'like'),
            'deviceType',
            'deviceEnabled'
        )
    ));

    public function beforeFilter()
    {
        parent::beforeFilter();

        if (!$this->Filter->Check('deviceEnabled'))
            $this->Filter->Write('deviceEnabled', true);
    }

    public function admin_index()
    {
        $totalDevices = $this->Device->find('count');
        $this->set('total_devices', $totalDevices);

        $this->set('devices', $this->Filter->filter(array(
                'conditions' => array('Device.agencyId' => CurrentAgency::agency('agencyId')),
                'order' => array('name' => 'asc'),
                'paginate' => true,
                'limit' => 25
            )));
    }

    public function admin_add()
    {
        $totalDevices = $this->Device->find('count');
        if ($totalDevices >= Licensing::maxDevices())
            return $this->redirect($this->referer('/admin/devices'));

        if (!$this->request->is('post'))
            return;
        
        foreach($this->request->data['Device'] as $key => $value)
            $this->request->data['Device'][$key] = trim($value);
        
        $this->Device->clear();
        $success = $this->Device->saveDevice(array_merge(
            $this->request->data['Device'],
            array('agencyId' => $this->CurrentAgency->agencyId)
        ));
        if (!$success)
        {
            $this->Session->setFlash('Failed to create the new device.');
            return;
        }

        // TODO: Notify ConnectCIC that it needs to reload its device table.
        $this->notifyDeviceChange();
        
        AuditLog::Log(AUDIT_DEVICE_CREATED, $this->request->data['Device']['name']);
        
        $this->Session->setFlash('The new device has been created.');
        return $this->redirect(array('action' => 'index'));
    }
    
    public function admin_edit($id)
    {
        $this->Device->id = $id;
        if ($this->request->is('get'))
        {
            $this->request->data = $this->Device->read();
            return;
        }
        
        foreach($this->request->data['Device'] as $key => $value)
            $this->request->data['Device'][$key] = trim($value);
        
        $oldName = $this->Device->field('name');
        if (!$this->Device->saveDevice($this->request->data))
        {                
            // Reload the device name so that it is available to the view.
            $this->request->data['Device']['name'] = $this->Device->field('name');
            $this->Session->setFlash('Failed to update the device.');
            return;
        }
        
        // HACK: Update the current device registration if we changed our device.
        if ($this->CurrentDevice->agencyDeviceId === $this->Device->id) {
            // TODO: This should use the CurrentDevice component.
            CakeSession::write('Device.deviceEnabled', $this->request->data['Device']['deviceEnabled']);
        }

        // TODO: Notify ConnectCIC that it needs to reload its device table.
        $this->notifyDeviceChange();
        
        AuditLog::Log(AUDIT_DEVICE_CHANGED, $oldName);

        $this->Session->setFlash('The device has been updated.');
        return $this->redirect(array('action' => 'index'));
    }

    public function admin_enable($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        if (!$this->Device->enable($id)) {
            $this->Session->setFlash('Failed to enable the device.');
        }
        else
        {
            $this->Device->id = $id;
            // HACK: Update the current device registration if we changed our device.
            if ($this->CurrentDevice->agencyDeviceId === $id) {
                // TODO: This should use the CurrentDevice component.
                CakeSession::write('Device.deviceEnabled', 1);
            }
            
            $this->notifyDeviceChange();

            $this->Session->setFlash('The device has been enabled.');
        }

        return $this->redirect(array('action' => 'index'));
    }

    public function admin_disable($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        if (!$this->Device->disable($id)) {
            $this->Session->setFlash('Failed to disable the device.');
        }
        else
        {
            $this->Device->id = $id;
            // HACK: Update the current device registration if we changed our device.
            if ($this->CurrentDevice->agencyDeviceId === $id) {
                // TODO: This should use the CurrentDevice component.
                CakeSession::write('Device.deviceEnabled', 0);
            }
            
            $this->notifyDeviceChange();

            $this->Session->setFlash('The device has been disabled.');
        }

        return $this->redirect(array('action' => 'index'));
    }
    
    public function admin_delete($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        $this->Device->id = $id;
        $oldName = $this->Device->field('name');

        // This will delete all requests/responses as well as it utilizes CakePHP's model relationships.
        if (!$this->Device->deleteDevice())
            $this->Session->setFlash('Failed to delete the device.');
        else
        {
            // TODO: Notify ConnectCIC that it needs to reload its device table.
            $this->notifyDeviceChange();
            
            $this->Session->setFlash('The device has been deleted.');
            AuditLog::Log(AUDIT_DEVICE_DELETED, $oldName);
        }
        
        return $this->redirect(array('action' => 'index'));
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.
    public function admin_forwarding($id=null)
    {
        if ($id != null)
            $this->request->data['Device']['agencyDeviceId'] = $id;

        if (!empty($this->request->data['Device']['agencyDeviceId']))
        {
            // Get the assigned forwarding devices
            $data = $this->Device->find('first', array(
                'fields' => array( 'agencyDeviceId', 'agencyId'),
                'contain' => array('Forward.agencyDeviceId', 'Forward.name'),
                'conditions' => array(
                    $this->Device->primaryKey => $this->request->data['Device']['agencyDeviceId']
                )
            ));
            
            $assigned = Set::combine($data, 'Forward.{n}.agencyDeviceId', 'Forward.{n}.name');
            
            // Get the unassigned permissions
            $devices = $this->Device->find('list', array(
                'conditions' => array(
                    'agencyId' => $data['Device']['agencyId'],
                    'agencyDeviceId !=' => $data['Device']['agencyDeviceId']
                )
            ));
            $unassigned = array_diff($devices, $assigned);
            
            $this->set('assigned', $assigned);
            $this->set('unassigned', $unassigned);
        }

        $this->set('Devices', $this->Device->find('list', array(
            'order' => 'name',
            'conditions' => array(
                'agencyId' => $this->CurrentAgency->agencyId
            )
        )));
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.  Maybe alter the UI
    //       so that it uses checkboxes?
    public function admin_forward()
    {
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();
        
        if (($this->request->data['Device']['assignAction'] == 'assign' && !empty($this->request->data['Forward'][0]['destinationAgencyDeviceId'])) ||
            ($this->request->data['Device']['assignAction'] == 'unassign' && !empty($this->request->data['Forward'][1]['destinationAgencyDeviceId'])))
        {
            $this->Device->contain(array('Forward.agencyDeviceId'));
            $data = $this->Device->find('first', array(
                'fields' => array('agencyDeviceId'),
                // TODO: Here we grab the destinationAgencyDeviceId.  This is different than the other assignments code
                //       from user groups, permission groups, etc, which grabs the HABTM table's primary key.  This is
                //       because the save code is expecting the 'associationForeignKey' column name.  The other code is
                //       in fact doing things incorrectly.
                'contain' => array('DeviceForward.destinationAgencyDeviceId'),
                'conditions' => array(
                    'agencyDeviceId' => $this->request->data['Device']['agencyDeviceId']
                )
            ));

            $count = count($data['Forward']);
            for($a = 0; $a < $count; ++$a)
            {
                $data['Forward'][$a]['destinationAgencyDeviceId'] = 
                    $data['Forward'][$a]['DeviceForward']['destinationAgencyDeviceId'];
                unset($data['Forward'][$a]['DeviceForward']);
            }

            if ($this->request->data['Device']['assignAction'] == 'unassign')
            {
                $toRemove = $this->request->data['Forward'][1]['destinationAgencyDeviceId'];
                foreach($toRemove as $id)
                {
                    $a = 0;
                    $count = count($data['Forward']);
                    while($a < $count)
                    {
                        if ($data['Forward'][$a]['destinationAgencyDeviceId'] == $id)
                        {
                            --$count;
                            array_splice($data['Forward'], $a, 1);
                            continue;
                        }

                        ++$a;
                    }
                }

                // If the array is empty, Cake uses a special placeholder indicating that all records should be deleted.
                if (empty($data['Forward']))
                    $data['Forward'][]['destinationAgencyDeviceId'] = null;
            }
            else
            {
                $toAdd = $this->request->data['Forward'][0]['destinationAgencyDeviceId'];
                foreach($toAdd as $id)
                    $data['Forward'][]['destinationAgencyDeviceId'] = $id;
            }
            
            if ($this->Device->save($data))
                $this->Session->setFlash('The forwarded devices have been updated.');
            else
                $this->Session->setFlash('Failed to save the forwarded devices.');
        }
        
        return $this->redirect(array('action' => 'forwarding', $this->request->data['Device']['agencyDeviceId']));
    }
    
    public function _adjustFilter($key, $model, $filter, $conditions) 
    {
        // The deviceType value for 'Fixed' is '0' in the database.  Sending 0 through the filter component
        // removes the filter (because it's "empty").  As a hack, we have the values in the filter drop down
        // increased by 1 (making 'Fixed' = '1').  Subtract 1 here to correct the filter value.
        if (!empty($conditions['Device.deviceType']))
            $conditions['Device.deviceType'] = ((int)$conditions['Device.deviceType']) - 1;
        
        return $conditions;
    }

    private function notifyDeviceChange()
    {
        $mq = ClassRegistry::init('MessageQueue');
        return $mq->save(array(
            'mailbox' => 'Dummy',
            'processState' => 'Queued',
            'message' => '<ServerInformation><Transaction>' .
                         '<Control><Command>Reconfigure</Command></Control>' .
                         '</Transaction></ServerInformation>'
        ));
    }
}
