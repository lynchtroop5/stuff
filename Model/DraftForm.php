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

// A join model which creates the many-to-many relationship between User and ConnectCicForm for the purposes of saving
// a user's form drafts.  This model isn't utilized by CakePHP's built-in relationship handling.  Instead, we have to
// manually create the relationships through hasMany and belongsTo.  This way we can access the draft's form values.
class DraftForm extends AppModel
{
	public $useTable     = 'tblAgencyUserDraftForm';
	public $primaryKey   = 'draftFormId';
	
	public $belongsTo = array(
		'User' => array(
			'foreignKey' => 'agencyUserId'
		)
	);

	public $hasOne = array(
		'Form' => array(
			'className' => 'ConnectCicForm',
			'foreignKey' => false,
			'conditions' => array('Draft.formId = Form.formId'),
			'dependent' => false
		)
	);
	
	public $hasMany = array(
		'DraftFormValue' => array(
			'foreignKey' => 'draftFormId',
			'dependent' => true,
			'exclusive' => true
		)
	);

	public $validate = array(
		'agencyUserId' => array(
			'notBlank' => array(
				'rule' => 'notBlank',
				'message' => 'Agency User ID is a required field.'
			),
			'exists' => array(
				'rule' => array('rowExists', 'User.agencyUserId'),
				'message' => 'Agency User ID must be the ID of an existing user.'
			)
		),
		'formId' => array(
			'notBlank' => array(
				'rule' => 'notBlank',
				'message' => 'Form ID is a required field.'
			),
			'exists' => array(
				'rule' => array('rowExists', 'Form.formId'),
				'message' => 'Form ID must be the ID of an existing user.'
			),
			'unique' => array(
				'rule' => array('unique', 'agencyUserId'),
				'message' => 'The user has already saved this form as a draft.'
			)
		)
	);
        
    public function getValues($userId, $draftId)
    {
        $result = $this->DraftFormValue->find('all', array(
            'fields' => array('DraftFormValue.connectCicTag', 'DraftFormValue.connectCicTagValue'),
            'contain' => array(
                'DraftForm' => array(
                    'conditions' => array(
                        'DraftForm.agencyUserId' => $userId
                    ),
                )
            ),
            'conditions' => array(
                'DraftFormValue.draftFormId' => $draftId
            ),
        ));

        return Set::combine($result, '{n}.DraftFormValue.connectCicTag', '{n}.DraftFormValue.connectCicTagValue');
    }
    
    public function saveDraft($userId, $formId, $values)
    {
        $existing = $this->find('first', array(
            'fields' => array('draftFormId'),
            'conditions' => array(
                'agencyUserId' => $userId,
                'formId' => $formId
            )
        ));

        if (!empty($existing))
            $this->delete($existing['DraftForm']['draftFormId']);
        
        $this->clear();
        $this->set(array(
            'DraftForm' => array(
                'agencyUserId' => $userId,
                'formId' => $formId
            ),
            'DraftFormValue' => array()
        ));

        foreach($values as $field => $value)
            $this->data['DraftFormValue'][] = array(
                'connectCicTag' => $field,
                'connectCicTagValue' => $value
            );

        return $this->saveAll();
	}
}
