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

class GroupsController extends AppController
{
    public function admin_index()
    {
        $this->paginate = array(
            'conditions' => array('agencyId' => $this->CurrentAgency->agencyId),
            'order'      => array('groupName' => 'asc'),
            'limit'      => 25
        );

        $this->set('groups', $this->paginate('Group'));
    }

    public function admin_add()
    {
        if (!$this->request->is('post'))
        {
            if (!$this->CurrentUser->isSystemAdmin()) {
                $groups = array(
                    array(
                        'id' => $this->CurrentAgency->agencyId,
                        'name' => $this->CurrentAgency->agencyName,
                        'groups' => $this->Group->find('list', array(
                            'conditions' => array('agencyId' => $this->CurrentAgency->agencyId)
                        ))
                    )
                );
            }
            else {
                $groups =$this->Group->Agency->find('all', array(
                    'fields' => array(
                        'Agency.agencyId', 'Agency.agencyName'
                    ),
                    'contain' => array('Group')
                ));
                
                // Converts the result into the same format above we generate
                // when we're not a sys admin.
                $groups = Hash::filter(Hash::map(
                    Hash::combine($groups, '{n}.Agency.agencyId', '{n}'),
                    '{n}',
                    function($array) {
                        if (!empty($array['Group'])) {
                            return array(
                                'id' => $array['Agency']['agencyId'],
                                'name' => $array['Agency']['agencyName'],
                                'groups' => Hash::combine($array, 'Group.{n}.agencyGroupId', 
                                    'Group.{n}.groupName')
                            );
                        }
                    }));
            }
            $this->set('groups', $groups);
            return;
        }

        foreach($this->request->data['Group'] as $key => $value)
            $this->request->data['Group'][$key] = trim($value);
        
        $ds = $this->Group->getDataSource();
        $ds->begin();

        unset($this->request->data['Group']['copyAgency']);
        $copyGroupId = null;
        if (!empty($this->request->data['Group']['copyGroupId'])) {
            $copyGroupId = $this->request->data['Group']['copyGroupId'];
            unset($this->request->data['Group']['copyGroupId']);
        }

        $this->Group->clear();
        $success = $this->Group->save(array_merge(
            $this->request->data['Group'],
            array('agencyId' => $this->CurrentAgency->agencyId)
        ));
        if (!$success)
        {
            $this->Session->setFlash('Failed to add the new group.');
            return;
        }
        
        if (!empty($copyGroupId))
        {
            $success = $this->Group->copyGroup($this->Group->id, $copyGroupId);
            if (!$success)
            {
                $this->Session->setFlash('Failed to add the new group.');
                return;
            }
        }

        $ds->commit();

        AuditLog::Log(AUDIT_PERMISSION_GROUP_CREATED, $this->request->data['Group']['groupName']);

        $this->Session->setFlash('The group has been added..');
        return $this->redirect(array('action' => 'index'));
    }
    
    public function admin_edit($id)
    {
        $this->Group->id = $id;
        if ($this->request->is('get'))
        {
            $this->request->data = $this->Group->read();
            return;
        }

        foreach($this->request->data['Group'] as $key => $value)
            $this->request->data['Group'][$key] = trim($value);
        
        $oldName = $this->Group->field('groupName');
        unset($this->request->data['Group']['agencyId']);
        if (!$this->Group->save($this->request->data))
        {
            $this->Session->setFlash('Failed to update the group.');
            return;
        }

        AuditLog::Log(AUDIT_PERMISSION_GROUP_RENAMED, $oldName,
            $this->request->data['Group']['groupName']);

        $this->Session->setFlash('The group has been updated.');
        return $this->redirect(array('action' => 'index'));
    }
    
    public function admin_delete($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        $this->Group->id = $id;
        $oldName = $this->Group->field('groupName');

        if (!$this->Group->delete())
            $this->Session->setFlash('Failed to delete the group.');
        else
        {
            $this->Session->setFlash('The group has been deleted.');
            AuditLog::Log(AUDIT_PERMISSION_GROUP_DELETED, $oldName);
        }

        return $this->redirect(array('action' => 'index'));
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.
    public function admin_assignments($id=null)
    {
        if ($id != null)
            $this->request->data['Group']['agencyGroupId'] = $id;

        if (!empty($this->request->data['Group']['agencyGroupId']))
        {
            $enableTestIndicator = Configure::read(
                'Interfaces.' . CurrentAgency::agency('interface') . '.enableTestIndicator');

            // Get the assigned permissions
            $data = $this->Group->find('first', array(
                'contain' => array('ClipsPermission.permissionId', 'ClipsPermission.shortDescription'),
                'conditions' => array(
                    $this->Group->primaryKey => $this->request->data['Group']['agencyGroupId']
                )
            ));
            $assigned = Set::combine($data, 'ClipsPermission.{n}.permissionId', 'ClipsPermission.{n}.shortDescription');
            
            // Get the unassigned permissions
            $permissions = $this->Group->ClipsPermission->find('list');
            $unassigned = array_diff($permissions, $assigned);

            // Hide the test only permission if it's not enabled for the interface
            if (!$enableTestIndicator) {
                unset($unassigned[13]);
            }

            $this->set('assigned', $assigned);
            $this->set('unassigned', $unassigned);
        }

        $this->set('Groups', $this->Group->find('list', array(
            'conditions' => array(
                'agencyId' => $this->CurrentAgency->agencyId
            )
        )));
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.  Maybe alter the UI
    //       so that it uses checkboxes?
    //
    // TODO: Cake deletes/re-adds all entries in the HABTM relationship by default.  So, we have to pass all the 
    //       permissions to be saved EVERY time we save.  This is a little slow, but it may not be a  problem since 
    //       changes to groups happen rarely.  We may need to change this so that the HABTM relationship is configured 
    //       with 'unique' property to false instead of true.
    public function admin_assign()
    {
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();
        
        if (($this->request->data['Group']['assignAction'] == 'assign' && !empty($this->request->data['ClipsPermission'][0]['permissionId'])) ||
            ($this->request->data['Group']['assignAction'] == 'unassign' && !empty($this->request->data['ClipsPermission'][1]['permissionId'])))
        {
            $this->Group->contain(array('ClipsPermission.permissionId'));
            $data = $this->Group->find('first', array(
                'fields' => array('agencyGroupId'),
                'contain' => array('ClipsPermission.permissionId'),
                'conditions' => array(
                    'agencyGroupId' => $this->request->data['Group']['agencyGroupId']
                )
            ));

            $count = count($data['ClipsPermission']);
            for($a = 0; $a < $count; ++$a)
                unset($data['ClipsPermission'][$a]['GroupPermission']);

            if ($this->request->data['Group']['assignAction'] == 'unassign')
            {
                $toRemove = $this->request->data['ClipsPermission'][1]['permissionId'];
                foreach($toRemove as $id)
                {
                    $a = 0;
                    $count = count($data['ClipsPermission']);
                    while($a < $count)
                    {
                        if ($data['ClipsPermission'][$a]['permissionId'] == $id)
                        {
                            --$count;
                            array_splice($data['ClipsPermission'], $a, 1);
                            continue;
                        }

                        ++$a;
                    }
                }

                // If the array is empty, Cake uses a special placeholder indicating that all records should be deleted.
                if (empty($data['ClipsPermission']))
                    $data['ClipsPermission'][]['forpermissionIdmId'] = null;
            }
            else
            {
                $toAdd = $this->request->data['ClipsPermission'][0]['permissionId'];
                foreach($toAdd as $id)
                    $data['ClipsPermission'][]['permissionId'] = $id;
            }

            if ($this->Group->save($data))
            {
                AuditLog::Log(AUDIT_PERMISSION_GROUP_CHANGED, $this->Group->field('groupName'));
                $this->Session->setFlash('The group assignments have been updated.');
            }
            else
                $this->Session->setFlash('Failed to save the group assignments.');
        }
        
        return $this->redirect(array('action' => 'assignments', $this->request->data['Group']['agencyGroupId']));
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.
    public function admin_form_assignments($id=null)
    {
        if ($id != null)
            $this->request->data['Group']['agencyGroupId'] = $id;

        if (!empty($this->request->data['Group']['agencyGroupId']))
        {
            // Get the assigned form groups
            $data = $this->Group->find('first', array(
                'fields' => array('agencyGroupId', 'agencyId'),
                'contain' => array(
                    'FormGroup' => array('agencyFormGroupId', 'formGroupName')
                ),
                'conditions' => array(
                    $this->Group->primaryKey => $this->request->data['Group']['agencyGroupId']
                )
            ));
            $assigned = Set::combine($data, 'FormGroup.{n}.agencyFormGroupId', 'FormGroup.{n}.formGroupName');

            // Get the unassigned form groups
            $formGroups = $this->Group->FormGroup->find('list', array(
                'conditions' => array(
                    'agencyId' => $data['Group']['agencyId']
                )
            ));
            $unassigned = array_diff($formGroups, $assigned);
            
            $this->set('assigned', $assigned);
            $this->set('unassigned', $unassigned);
        }

        $this->set('Groups', $this->Group->find('list', array(
            'conditions' => array(
                'agencyId' => $this->CurrentAgency->agencyId
            )
        )));
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.  Maybe alter the UI
    //       so that it uses checkboxes?  Also, this could be ported to a component since the code is identical except
    //       for column names.
    //
    // TODO: Cake deletes/re-adds all entries in the HABTM relationship by default.  So, we have to pass all the 
    //       form groups to be saved EVERY time we save.  This is a little slow, but it may not be a  problem since 
    //       changes to groups happen rarely.  We may need to change this so that the HABTM relationship is configured 
    //       with 'unique' property to false instead of true.
    public function admin_form_assign()
    {
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();
        
        if (($this->request->data['Group']['assignAction'] == 'assign' && !empty($this->request->data['FormGroup'][0]['agencyFormGroupId'])) ||
            ($this->request->data['Group']['assignAction'] == 'unassign' && !empty($this->request->data['FormGroup'][1]['agencyFormGroupId'])))
        {
            $this->Group->contain(array('FormGroup.agencyFormGroupId'));
            $data = $this->Group->find('first', array(
                'fields' => array('agencyGroupId'),
                'contain' => array('FormGroup.agencyFormGroupId'),
                'conditions' => array(
                    'agencyGroupId' => $this->request->data['Group']['agencyGroupId']
                )
            ));

            $count = count($data['FormGroup']);
            for($a = 0; $a < $count; ++$a)
                unset($data['FormGroup'][$a]['GroupFormAccess']);

            if ($this->request->data['Group']['assignAction'] == 'unassign')
            {
                $toRemove = $this->request->data['FormGroup'][1]['agencyFormGroupId'];
                foreach($toRemove as $id)
                {
                    $a = 0;
                    $count = count($data['FormGroup']);
                    while($a < $count)
                    {
                        if ($data['FormGroup'][$a]['agencyFormGroupId'] == $id)
                        {
                            --$count;
                            array_splice($data['FormGroup'], $a, 1);
                            continue;
                        }

                        ++$a;
                    }
                }

                // If the array is empty, Cake uses a special placeholder indicating that all records should be deleted.
                if (empty($data['FormGroup']))
                    $data['FormGroup'][]['agencyFormGroupId'] = null;
            }
            else
            {
                $toAdd = $this->request->data['FormGroup'][0]['agencyFormGroupId'];
                foreach($toAdd as $id)
                    $data['FormGroup'][]['agencyFormGroupId'] = $id;
            }


            if ($this->Group->save($data))
            {
                AuditLog::Log(AUDIT_PERMISSION_GROUP_CHANGED, $this->Group->field('groupName'));
                $this->Session->setFlash('The group assignments have been updated.');
            }
            else
                $this->Session->setFlash('Failed to save the group assignments.');
        }
        
        return $this->redirect(array('action' => 'form_assignments', $this->request->data['Group']['agencyGroupId']));
    }
}
