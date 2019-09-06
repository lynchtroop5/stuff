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

// Represent's an agency permission Group which is used to control access to various parts of CLIPS's administrative
// functions or a user's access to Forms.  Access to forms is controlled by the joining model GroupFormAccess which
// can be assigned individual forms or an agency's form group.  Access to permissions is controlled by the joining model
// GroupPermission.
class Group extends AppModel
{
	public $useTable     = 'tblAgencyGroup';
	public $primaryKey   = 'agencyGroupId';
	public $displayField = 'groupName';

	public $belongsTo = array(
		'Agency' => array(
			'foreignKey' => 'agencyId'
		)
	);

	public $hasAndBelongsToMany = array(
		'FormGroup'  => array(
			'with' => 'GroupFormAccess',
			'foreignKey' => 'agencyGroupId',
			'associationForeignKey' => 'agencyFormGroupId',
			'conditions' => array('GroupFormAccess.agencyFormGroupId is not null')
		),
		'Form' => array(
			'className' => 'ConnectCicForm',
			'with' => 'GroupFormAccess',
			'foreignKey' => 'agencyGroupId',
			'associationForeignKey' => 'formId',
			'conditions' => array('GroupFormAccess.agencyFormGroupId is null'), // Ensures proper index use
		),
		'ClipsPermission' => array(
			'with' => 'GroupPermission',
			'foreignKey' => 'agencyGroupId',
			'associationForeignKey' => 'permissionId',
		)
	);

	public $validate = array(
		'agencyId' => array(
			'rule' => array('rowExists', 'Agency.agencyId'),
			'message' => 'Agency ID must be the ID of an existing agency.'
		),
		'groupName' => array(
			'value' => array(
				'rule' => 'notBlank',
				'message' => 'Group name is a required field.'
			),
			array(
                'on' => 'create',
				'rule' => array('unique', 'agencyId'),
				'message' => 'A group with this name already exists.'
			)
		)
	);
	
	public function copyGroup($destGroupId, $sourceGroupId)
	{
		if (!is_numeric($destGroupId) || !is_numeric($sourceGroupId)) {
			return false;
		}

		$ds = $this->getDataSource();

		// Copy missing form groups.
		$sql = "declare @copyGroups table (formGroupName varchar(64))
declare @sourceAgencyId bigint
declare @sourceGroupId bigint
declare @destAgencyId bigint
declare @destGroupId bigint

set @sourceGroupId = $sourceGroupId
set @destGroupId = $destGroupId

select @sourceAgencyId = agencyId 
from tblAgencyGroup 
where agencyGroupId = @sourceGroupId

select @destAgencyId = agencyId 
from tblAgencyGroup 
where agencyGroupId = @destGroupId

-- Copy permissions
insert into tblAgencyGroupPermission (agencyGroupId, permissionId)
select @destGroupId, permissionId
from tblAgencyGroupPermission
where agencyGroupId = @sourceGroupId

-- Get a list of groups to copy
insert into @copyGroups (formGroupName)
select formGroupName
from tblAgencyFormGroup
where agencyId = @sourceAgencyId
and formGroupName not in(select formGroupName 
							from tblAgencyFormGroup 
							where agencyId = @destAgencyId)


-- Copy groups
insert into tblAgencyFormGroup (agencyId, formGroupName)
select @destAgencyId, formGroupName
from @copyGroups

-- Copy new group forms
insert into tblAgencyFormGroupForm (agencyFormGroupId, formId)
select d.agencyFormGroupId, f.formId
from @copyGroups cg
inner join tblAgencyFormGroup s
on s.agencyId = @sourceAgencyId and s.formGroupName = cg.formGroupName
inner join tblAgencyFormGroup d
on d.agencyId = @destAgencyId and d.formGroupName = cg.formGroupName
inner join tblAgencyFormGroupForm f
on f.agencyFormGroupId = s.agencyFormGroupId

-- Copy the form group to permission group assignments
insert into tblAgencyGroupFormAccess (agencyGroupId, agencyFormGroupId, formId)
select @destGroupId, d.agencyFormGroupId, a.formId
from tblAgencyGroupFormAccess a
inner join tblAgencyFormGroup s
on s.agencyFormGroupId = a.agencyFormGroupId
inner join tblAgencyFormGroup d
on d.agencyId = @destAgencyId and d.formGroupName = s.formGroupName
where a.agencyGroupId = @sourceGroupId";

		return $ds->execute($sql);
	}
	
    public function assignAdministratorPermissions($options=array())
    {
        if (empty($this->id))
            throw new LogicException('Group ID required.');
        
        $permissions = $this->ClipsPermission->findAdminPermissions(array('permissionId'));
     
        $options = array_merge(array('atomic' => false), $options);
        if (!$this->transaction(null, $options['atomic']))
            return false;
        
        $data = array(
            $this->primaryKey => $this->id,
            //$this->ClipsPermission->alias => array(
                'ClipsPermission' => Set::extract($permissions, '/ClipsPermission/permissionId')
            //)
        );
        $success = $this->save($data);

        return $this->transaction($success, $options['atomic']);
    }
}
