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
class FormGroupsController extends AppController
{
    public function admin_index()
    {
        $this->paginate = array(
            'conditions' => array('agencyId' => $this->CurrentAgency->agencyId),
            'order'      => array('groupName' => 'asc'),
            'limit'      => 25
        );

        $this->set('form_groups', $this->paginate('FormGroup'));
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
                        'groups' => $this->FormGroup->find('list', array(
                            'conditions' => array('agencyId' => $this->CurrentAgency->agencyId)
                        ))
                    )
                );
            }
            else {
                $groups =$this->FormGroup->Agency->find('all', array(
                    'fields' => array(
                        'Agency.agencyId', 'Agency.agencyName'
                    ),
                    'contain' => array('FormGroup')
                ));
                
                // Converts the result into the same format above we generate
                // when we're not a sys admin.
                $groups = Hash::filter(Hash::map(
                    Hash::combine($groups, '{n}.Agency.agencyId', '{n}'),
                    '{n}',
                    function($array) {
                        if (!empty($array['FormGroup'])) {
                            return array(
                                'id' => $array['Agency']['agencyId'],
                                'name' => $array['Agency']['agencyName'],
                                'groups' => Hash::combine($array, 'FormGroup.{n}.agencyFormGroupId', 
                                    'FormGroup.{n}.formGroupName')
                            );
                        }
                    }));
            }
            $this->set('groups', $groups);
            return;
        }

        foreach($this->request->data['FormGroup'] as $key => $value)
            $this->request->data['FormGroup'][$key] = trim($value);
        
        $ds = $this->FormGroup->getDataSource();
        $ds->begin();

        unset($this->request->data['FormGroup']['copyAgency']);
        $copyFormGroupId = null;
        if (!empty($this->request->data['FormGroup']['copyFormGroupId'])) {
            $copyFormGroupId = $this->request->data['FormGroup']['copyFormGroupId'];
            unset($this->request->data['FormGroup']['copyFormGroupId']);
        }

        $this->FormGroup->clear();
        $success = $this->FormGroup->save(array_merge(
            $this->request->data['FormGroup'],
            array('agencyId' => $this->CurrentAgency->agencyId)
        ));
        if (!$success)
        {
            $ds->rollback();
            $this->Session->setFlash('Failed to add the new form group.');
            return;
        }
        
        if (!empty($copyFormGroupId)) {
            // TODO: We need to check to see if we really have access to the source
            //       form group ID
            $success = $this->FormGroup->FormGroupForm->copyForms(
                $this->FormGroup->id, 
                $copyFormGroupId);
            if (!$success)
            {
                $ds->rollback();
                $this->Session->setFlash('Failed to add the new form group.');
                return;
            }
        }

        $ds->commit();

        AuditLog::Log(AUDIT_FORM_GROUP_CREATED, $this->request->data['FormGroup']['formGroupName']);
        
        $this->Session->setFlash('The form group has been added.');
        return $this->redirect(array('action' => 'index'));
    }
    
    public function admin_edit($id)
    {
        $this->FormGroup->id = $id;
        if ($this->request->is('get'))
        {
            $this->request->data = $this->FormGroup->read();
            return;
        }

        foreach($this->request->data['FormGroup'] as $key => $value)
            $this->request->data['FormGroup'][$key] = trim($value);
        
        $oldName = $this->FormGroup->field('formGroupName');
        unset($this->request->data['FormGroup']['agencyId']);
        if (!$this->FormGroup->save($this->request->data))
        {
            $this->Session->setFlash('Failed to update the form group.');
            return;
        }

        AuditLog::Log(AUDIT_FORM_GROUP_RENAMED, $oldName,
            $this->request->data['FormGroup']['formGroupName']);

        $this->Session->setFlash('The form group has been updated.');
        return $this->redirect(array('action' => 'index'));
    }
    
    public function admin_delete($id)
    {
        if (!$this->request->is('put') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        $this->FormGroup->id = $id;
        $oldName = $this->FormGroup->field('formGroupName');
        if (!$this->FormGroup->delete())
            $this->Session->setFlash('Failed to delete the form group.');
        else
        {
            $this->Session->setFlash('The form group has been deleted.');
            AuditLog::Log(AUDIT_FORM_GROUP_DELETED, $oldName);
        }   

        return $this->redirect(array('action' => 'index'));
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.
    public function admin_assignments($id=null)
    {
        if ($id != null)
            $this->request->data['FormGroup']['agencyFormGroupId'] = $id;

        if (!empty($this->request->data['FormGroup']['agencyFormGroupId']))
        {
            // This gets the first form group based on the id (which should be only one, anyway)
            // And then includes all the assigned forms by using the containable behavior.
            $data = $this->FormGroup->find('first', array(
                'contain' => array(
                    'Agency' => array(
                        'fields' => array('agencyId', 'interface'),
                    ),
                    'ConnectCicForm' => array(
                        'fields' => array('formId', 'formInterfaceTitle'),
                        'conditions' => array('ConnectCicForm.interface' => $this->CurrentAgency->interface)
                    )
                ),
                'conditions' => array(
                    'agencyFormGroupId' => $this->request->data['FormGroup']['agencyFormGroupId']
                )
            ));
            $assigned = Set::combine($data, 'ConnectCicForm.{n}.formId', 'ConnectCicForm.{n}.formInterfaceTitle');
            
            // Get the unassigned forms
            $forms = $this->FormGroup->ConnectCicForm->find('list', array(
                'conditions' => array(
                    'interface' => $data['Agency']['interface'],
                    'isValid' => 1
                )
            ));
            $unassigned = array_diff($forms, $assigned);
            
            $this->set('assigned', $assigned);
            $this->set('unassigned', $unassigned);
        }

        $this->set('formGroups', $this->FormGroup->find('list', array(
            'conditions' => array(
                'agencyId' => $this->CurrentAgency->agencyId
            )
        )));
    }

    // TODO: Clean this up.  Cake can better handle HABTM relationships than what this code does.  Maybe alter the UI
    //       so that it uses checkboxes?
    //
    // TODO: Cake deletes/re-adds all entries in the HABTM relationship by default.  So, we have to pass all the forms
    //       to be saved EVERY time we save.  This is a little slow, but it may not be a  problem since changes to form
    //       groups happen rarely.  We may need to change this so that the HABTM relationship is configured with 
    //       'unique' property to false instead of true.
    public function admin_assign()
    {        
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();
        
        if (($this->request->data['FormGroup']['assignAction'] == 'assign' && !empty($this->request->data['ConnectCicForm'][0]['formId'])) ||
            ($this->request->data['FormGroup']['assignAction'] == 'unassign' && !empty($this->request->data['ConnectCicForm'][1]['formId'])))
        {
            $this->FormGroup->contain(array('ConnectCicForm.formId'));
            $data = $this->FormGroup->find('first', array(
                'fields' => array('agencyFormGroupId'),
                'contain' => array('ConnectCicForm' => array(
                    'fields' => array('formId'),
                    'conditions' => array('isValid' => 1)
                )),
                'conditions' => array(
                    'agencyFormGroupId' => $this->request->data['FormGroup']['agencyFormGroupId']
                )
            ));

            // Remove the joining table data.
            $count = count($data['ConnectCicForm']);
            for($a = 0; $a < $count; ++$a)
                unset($data['ConnectCicForm'][$a]['FormGroupForm']);

            if ($this->request->data['FormGroup']['assignAction'] == 'unassign')
            {
                $toRemove = $this->request->data['ConnectCicForm'][1]['formId'];
                foreach($toRemove as $id)
                {
                    $a = 0;
                    $count = count($data['ConnectCicForm']);
                    while($a < $count)
                    {
                        if ($data['ConnectCicForm'][$a]['formId'] == $id)
                        {
                            --$count;
                            array_splice($data['ConnectCicForm'], $a, 1);
                            continue;
                        }

                        ++$a;
                    }
                }

                // If the array is empty, Cake uses a special placeholder indicating that all records should be deleted.
                if (empty($data['ConnectCicForm']))
                    $data['ConnectCicForm'][]['formId'] = null;
            }
            else
            {
                $toAdd = $this->request->data['ConnectCicForm'][0]['formId'];
                foreach($toAdd as $id)
                    $data['ConnectCicForm'][]['formId'] = $id;
            }
            
            if ($this->FormGroup->save($data))
            {
                $this->Session->setFlash('The form assignments have been updated.');
                AuditLog::Log(AUDIT_FORM_GROUP_CHANGED, $this->FormGroup->field('formGroupName'));
            }
            else
                $this->Session->setFlash('Failed to save the form assignments.');
        }

        return $this->redirect(array('action' => 'assignments', $this->request->data['FormGroup']['agencyFormGroupId']));
    }
}
