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

class FormsController extends AppController
{
    public $uses = array('ConnectCicForm', 'User', 'DraftForm', 'FavoriteForm', 'ArchiveRequest',
                         'MessageQueue', 'Prefill', 'ConnectCic', 'UserSession');
    public $components = array('CriminalHistorySession', 'CurrentRequest', 'Filter' => array(
        'key' => 'FormList',
        'modelClass' => false,
        'fields' => array('text', 'sectionId')
    ));
    public $helpers = array('CriminalHistorySession');

    public function beforeFilter()
    {
        parent::beforeFilter();

        // The filter criteria is applied (in the startup method) after the call to beforeFilter, according to cake
        // documentation.  We need to be able to detect if the user submitted a text or form group option so that we
        // can disable the other.

        // We had text stored in the filter, and the user is now submitting a group.
        if (!empty($this->request->data['Filter']['FormList']['text']))
            unset($this->request->data['Filter']['FormList']['sectionId']);
    }

    public function isAuthorized()
    {
        return (
            !$this->CurrentUser->isSystemAdmin()
            && $this->CurrentDevice->registered()
            && $this->CurrentDevice->stateid()
            && $this->CurrentUser->hasPermission('runTransaction')
        );
    }

    public function index($requestId=null, $responseId=null)
    {
        if ($this->Mobile->isMobile() == true) {
            $this->ConnectCic->validationErrors = $this->Session->read('ValidationErrors');
            $this->set('failed_form', $this->Session->read('FailedForm'));
            $this->request->data['ConnectCic'] = $this->Session->read('FailedFormData');
            $this->Session->delete('ValidationErrors');
            $this->Session->delete('FailedForm');
            $this->Session->delete('FailedFormData');
            return;
        }

        $this->layout = 'browser';
        $groups = array('0' => '< All Forms >', '-1' => '< Favorite Forms >', '-2' => '< Saved Incomplete Forms >') +
            $this->User->getSections($this->CurrentUser->agencyUserId);

        // Get the active form section from the session
        $sectionId = $this->Filter->read('sectionId');
        if (empty($sectionId) && !is_numeric($sectionId))
        {
            $sectionId = -1;
            // TODO: This should use find('count') or similar alternative
            $favorites = $this->User->getFavoriteForms($this->CurrentUser->agencyUserId, -1);
            if (count($favorites) <= 0)
                $sectionId = 0;

            $this->Filter->write('sectionId', $sectionId);
        }

        if (!empty($requestId))
        {
            if (!is_numeric($requestId))
            {
                // An actual form ID was passed.  This is used when a prefill request is issued and the caller wants
                // to auto-populate the form.
                $formId = $requestId;
                // Hack to deal with prefill needing the form Id
                $this->Session->write('Form.formId', $formId);
                if (!empty($this->request->params['named']['prefillId']))
                {
                    $this->prefill($this->request->params['named']['prefillId']);
                    $this->delete_prefill($this->request->params['named']['prefillId']);
                }
                $requestId = null;
            }
            else
            {
                // TODO: This is a hack to correctly set the Criminal History Session form when loaded from
                //       the original request; Without this, CLIPS would incorrectly show the criminal history
                //       audit form if a criminal history form was used, but a normal form was loaded from the
                //       response screen.
                $values = $this->ArchiveRequest->find('first', array(
                    'fields' => array('formId'),
                    'conditions' => array(
                        'ArchiveRequest.requestId' => $requestId
                    )
                ));
                $formId = $values['ArchiveRequest']['formId'];
            }
            $formName = substr($formId, strlen($this->CurrentAgency->interface) + 1);

            $this->CriminalHistorySession->setForm($this->CurrentAgency->interface, $formId, $formName);
            $this->Session->write('Form.formId', $formId);
        }

        // Force the forwarding form if a response ID was passed.
        if (!empty($responseId))
        {
            $formId = $this->CurrentAgency->interface . '_' .
                Configure::read('Interfaces.' . $this->CurrentAgency->interface . '.forwardForm')[0];
            $this->Session->write('Form.formId', $formId);
        }

        $this->set('requestId', $requestId);
        $this->set('responseId', $responseId);
        $this->set('groups', $groups);
    }

    public function form_list()
    {
        $groupId = $this->Filter->read('sectionId');
        switch($groupId)
        {
        case 0:  // All
            $forms = $this->User->getAllForms($this->CurrentUser->agencyUserId,  $this->Filter->conditions());/*
            $forms = $this->ConnectCicForm->find('all', array(
                'fields' => array('formId', 'interface', 'formName', 'formMessageKey', 'formTitle',
                                  'formShortDescription',  'formDescription', 'isFavoriteForm'),
                'conditions' => array(
                    'interface' => $this->CurrentAgency->interface,
                    'isValid' => 1
                ) + $this->Filter->conditions()
            ));*/
            break;

        case -2: // Drafts
            $forms = $this->User->getDraftForms($this->CurrentUser->agencyUserId, $groupId);
            break;

        case -1: // Favorites
            // TODO: This should use find('favorite')
            $forms = $this->User->getFavoriteForms($this->CurrentUser->agencyUserId, $groupId);
            break;

        default: // Specific group
            $forms = $this->User->getSectionForms($this->CurrentUser->agencyUserId, $groupId);
            break;
        }

        $this->set('forms', $forms);
    }

    public function form($formId=null, $requestId=null, $responseId=null)
    {
        // Check if current form supports prefill.
        if ($this->check_form($formId)) {
            $prefill_requests = $this->Prefill->find('list', array(
                'conditions' => array(
                    'agencyId' => $this->CurrentAgency->agencyId,
                    'deviceAlias' => $this->CurrentDevice->deviceAlias
                )
            ));
            $this->set('prefill_requests', $prefill_requests);
        } else {
            $this->set('prefill_requests', false);
        }

        // Fix for when this is called by requestAction with no formId
        if (is_numeric($formId) && $requestId === null)
        {
            $requestId = $formId;
            $formId = null;
        }

        // Load the request values
        $values = array();
        if (!empty($requestId))
        {
            $values = $this->ArchiveRequest->find('first', array(
                'contain' => array(
                    'ArchiveRequestValue' => array(
                        'fields' => array('connectCicTag', 'connectCicTagValue')
                    ),
                ),
                'fields' => array('formId'),
                'conditions' => array(
                    'ArchiveRequest.requestId' => $requestId
                )
            ));

            // Change the formId so CLIPS changes forms.
            $formId = $values['ArchiveRequest']['formId'];

            // Convert the values into key-value pairs.
            $values = Set::combine($values,
                'ArchiveRequestValue.{n}.connectCicTag',
                'ArchiveRequestValue.{n}.connectCicTagValue');
            $this->set('form_values', $values);
        }

        $forms = $this->User->getAllForms($this->CurrentUser->agencyUserId,
            array('ConnectCicForm.formId' => $formId));
        if (empty($forms))
            throw new NotFoundException();

        if (!empty($responseId))
        {
            $this->ArchiveRequest->ArchiveResponse->id = $responseId;
            $tag = Configure::read('Interfaces.' . $this->CurrentAgency->interface . '.forwardForm')[1];
            $values[$tag] = $this->ArchiveRequest->ArchiveResponse->field('messageData');
        }

        // Determine the correct form to load
        if (empty($formId))
        {
            $formId = $this->Session->read('Form.formId');
            if ($formId == null)
                return;
        }
        else
            $this->Session->write('Form.formId', $formId);

        $form = $this->readDefinition($formId);

        $definition = $form['ConnectCicFormStage'];
        $form = $form['ConnectCicForm'];

        $html = $form['htmlForm'];
        unset($form['htmlForm']);

        $this->CriminalHistorySession->setForm($this->CurrentAgency->interface, $formId, $form['formName']);

        // Display the form if we're not a criminal history form, or the transaction has been audited.
        if ($this->CriminalHistorySession->auditRequired())
            return $this->redirect(array('controller' => 'criminal_histories', 'action' => 'audit'), null, true);

        // Transform the form stage contents into a series of lookup tables.
        $definition = $this->processDefinition($definition);

        // Convert the HTML template into an actual form
        $html = $this->populateForm($form, $html, $definition);

        // If we're pulling a draft, pull its values.
        if (!empty($this->request->params['named']['draftFormId']))
        {
            $draftForm = ClassRegistry::init('DraftForm');

            $values = $draftForm->getValues($this->CurrentUser->agencyUserId,
                $this->request->params['named']['draftFormId']);
        }

        $this->set('form_notes', $this->getNotes($formId));
        $this->set('form_html', $html);

        if (!empty($values))
            $this->set('form_values', $values);

        // Hand it all off to the view.
        $this->set('form', $form);
    }

    public function check_form($formId)
    {
        $formName = substr($formId, strrpos($formId, '_') + 1);
        $results = $this->Prefill->PrefillValue->find('all', array(
            'fields' => array(
                'PrefillMap.prefillMapId'
            ),
            'contain' => array('PrefillMap'),
            'conditions' => array(
                'PrefillMap.interface' => $this->CurrentAgency->interface,
                'PrefillMap.formName' => $formName
            )
        ));
        return count($results) > 0 ? true : false;
    }

    public function prefill($prefillId)
    {
        $formId = $this->Session->read('Form.formId');
        $formName = substr($formId, strrpos($formId, '_') + 1);

        $results = $this->Prefill->PrefillValue->find('all', array(
            'fields' => array(
                'PrefillMap.fieldName',
                'PrefillValue.tag',
                'PrefillValue.value'
            ),
            'contain' => array('PrefillMap'),
            'conditions' => array(
                'PrefillValue.prefillId' => $prefillId,
                'OR' => array(
                    'AND' => array(
                        'PrefillMap.interface' => $this->CurrentAgency->interface,
                        'PrefillMap.formName' => $formName
                    ),
                    'PrefillValue.category' => 'Internal'
                )
            )
        ));

        $values = array();
        foreach($results as $prefillValue)
        {
            $key = ((!empty($prefillValue['PrefillMap']['fieldName']))
                ? $prefillValue['PrefillMap']['fieldName']
                : $prefillValue['PrefillValue']['tag']);
            $values[$key] = $prefillValue['PrefillValue']['value'];
        }

        $this->set('form_values', $values);

        // HACK: For thurston county, we add an event to trigger a plugin listener.
        // TODO: We can actually use this hack more generally to allow extension in
        //       other places.
        $this->getEventManager()->dispatch(new CakeEvent('Clips.Controller.Forms.afterPrefill', $this, array(
            'prefillId' => $prefillId,
            'formId' => $formId,
            'formValues' => $values
        )));
    }

    public function prefill_list()
    {
        $prefill_requests = $this->Prefill->find('list', array(
            'conditions' => array(
                'agencyId' => $this->CurrentAgency->agencyId,
                'deviceAlias' => $this->CurrentDevice->deviceAlias
            )
        ));
        $this->set('prefill_requests', $prefill_requests);
    }

    public function delete_prefill($prefillId=null)
    {
        if ($prefillId === null)
        {
            if (!$this->request->is('post'))
                throw new MethodNotAllowedException();
            $prefillId = $this->request->data['Prefill']['prefillId'];
        }

        $this->Prefill->delete($prefillId);

        if ($this->request->is('post'))
            exit;
    }

    public function submit($formName=null)
    {
        $formId = null;
        $values = array();

        if (!$this->request->is('requested') && !$this->request->is('post'))
            throw new MethodNotAllowedException();

        if ($this->Mobile->isMobile() && !empty($this->request->data['Meta']['MobileForm']))
        {
            $this->ConnectCic->setForm($this->request->data['Meta']['MobileForm']);
            $this->ConnectCic->set($this->request->data['ConnectCic']);
            if (!$this->ConnectCic->validates())
            {
                $this->Session->write('FailedForm', $this->request->data['Meta']['MobileForm']);
                $this->Session->write('ValidationErrors', $this->ConnectCic->validationErrors);
                $this->Session->write('FailedFormData', $this->request->data['ConnectCic']);
                return $this->redirect(array('controller' => 'forms', 'action' => 'index'));
            }

            $formMap = array(
                'vehicle' => 'QQ',
                'person-oln' => 'QQ',
                'person-name' => 'QQ',
                'gun' => '220',
                'article' => '200'
            );

            $formName = $this->request->data['Meta']['MobileForm'];
            if (!in_array($formName, array_keys($formMap)))
                throw new MethodNotAllowedException();

            $formName = $formMap[$formName];
            $formId = $this->CurrentAgency->interface . '_' . $formName;
        }

        if (!$this->request->is('requested'))
        {
            switch($this->request->data['Meta']['button'])
            {
            case 'Save Draft':      return $this->save_draft();
            case 'Delete Draft':    return $this->delete_draft();
            case 'Save Favorite':   return $this->save_favorite();
            case 'Delete Favorite': return $this->deleteFavorite();
            }

            if ($this->CriminalHistorySession->isCriminalHistoryForm() && !$this->CriminalHistorySession->published())
            {
                if (!$this->CriminalHistorySession->publish())
                    throw new UnexpectedValueException('Could not publish criminal history information.');
            }
        }

        // Handle requests passed from the command line controller.
        if ($formName !== null && !empty($this->request->params['named']['CommandLine']['values']))
        {
            $formId = $this->CurrentAgency->interface . '_' . $formName;
            $values = $this->request->params['named']['CommandLine']['values'];
        }

        // Submit the transaction to ConnectCIC.  If this was a draft form, delete the draft.
        $this->submitToConnectCic($formId, $values);
        if (!empty($this->request->data['Meta']['draftFormId']))
            $this->DraftForm->delete($this->request->data['Meta']['draftFormId']);

        if (!$this->request->is('requested'))
            return $this->redirect(array('controller' => 'responses', 'action' => 'index'), null, true);
    }

    public function save_draft()
    {
        if ($this->Session->read('Form.formId') && !empty($this->request->data['ConnectCic']))
        {
            $this->DraftForm->saveDraft(
                $this->CurrentUser->agencyUserId,
                $this->Session->read('Form.formId'),
                $this->request->data['ConnectCic']);
        }

        if ($this->request->is('ajax'))
        {
            $this->autoRender = false;
            return;
        }

        return $this->redirect(array('action' => 'index'), null, true);
    }

    public function delete_draft()
    {
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();

        if (!empty($this->request->data['Meta']['draftFormId']))
            $this->DraftForm->delete($this->request->data['Meta']['draftFormId']);

        if ($this->request->is('ajax'))
        {
            $this->autoRender = false;
            return;
        }

        return $this->redirect(array('action' => 'index'), null, true);
    }

    public function save_favorite()
    {
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();

        if ($this->Session->read('Form.formId'))
            $this->FavoriteForm->saveFavorite(
                $this->CurrentUser->agencyUserId,
                $this->Session->read('Form.formId'));

        if ($this->request->is('ajax'))
        {
            $this->autoRender = false;
            return;
        }

        return $this->redirect(array('action' => 'index'), null, true);
    }

    public function delete_favorite()
    {
        if ($this->request->is('get'))
            throw new MethodNotAllowedException();


        if ($this->Session->read('Form.formId'))
            $this->FavoriteForm->deleteFavorite(
                $this->CurrentUser->agencyUserId,
                $this->Session->read('Form.formId'));

        if ($this->request->is('ajax'))
        {
            $this->autoRender = false;
            return;
        }

        return $this->redirect(array('action' => 'index'), null, true);
    }

    public function _adjustFilter($key, $model, $filter, $results)
    {
        if (!empty($filter['text']))
        {
            if ($filter['text'][0] == '!')
            {
                $results = array(
                    'formMessageKey =' => substr($filter['text'], 1)
                );
            }
            else
            {
                $results['or'] = array(
                    'formName like' => FilterComponent::fromMask($filter['text']),
                    'formMessageKey like' => FilterComponent::fromMask($filter['text']),
                    'formTitle like' => FilterComponent::fromMask($filter['text'])
                );
            }
        }

        return $results;
    }

    private function readDefinition($formId, $full=false)
    {
        $options = array(
            'fields' => array('formId', 'formName', 'formMessageKey', 'formTitle', 'formDescription', 'htmlForm',
                              'isFavoriteForm'),
            'contain' => array('ConnectCicFormStage' => array(
                'fields' => array('seqNum', 'rowNum', 'fieldTag', 'fieldLength', 'displayLength', 'fieldLabel',
                                  'fieldContinuation', 'fieldBaseTag', 'defaultValue', 'valueListName', 'dataType',
                                  'fieldOptions', 'repeatCount', 'englishLabel', 'relatedFieldTag', 'usageCode',
                                  'refComment'),
                'conditions' => array(),
                'order' => 'seqNum'
            )),
            'conditions' => array(
                'formId' => $formId,
                'isValid' => 1
            )
        );

        // If we're not grabbing the full definition, include only fields.
        if ($full == false)
            $options['contain']['ConnectCicFormStage']['conditions'][] = 'fieldTag is not null';

        return $this->ConnectCicForm->find('first', $options);
    }

    private function processDefinition($fieldDefinitions)
    {
        $dropDowns = array();
        $default = array(null, null, 1);
        $return = array('hidden' => array());

        foreach($fieldDefinitions as $definition)
        {
            switch($definition['fieldTag'])
            {
            case 'BadgeNumber':
            case 'OperatorPin':
            case 'UserID':
                if (Configure::read('Clips.agency.enableFormUserNamePrefill')) {
                    $definition['defaultValue'] = $this->CurrentUser->stateUserId;
                }
                break;

            case 'ORI':
                $definition['valueListName'] = '$_ORI_DD_$';
                if (Configure::read('Clips.agency.enableFormOriPrefill')) {
                    $definition['defaultValue'] = $this->CurrentDevice->ori;
                }
                break;

            case 'OriginatingAgencyORI':
                $definition['valueListName'] = '$_ORI_DD_$';
                if (Configure::read('Clips.agency.enableFormOriPrefill')) {
                    $definition['defaultValue'] = $this->CurrentAgency->ori;
                }
                break;

            case 'FormORI':
                $definition['valueListName'] = '$_ORI_DD_$';
                break;
            }

            if (strtolower($definition['dataType']) == 'hidden')
                $return['hidden'][] = $definition;

            // Store the XML Tag in the first index and number in the second.
            if (!preg_match('/([_A-Za-z]+)([0-9]+)?/', $definition['fieldTag'], $match))
                continue;

            if (!empty($definition['valueListName']))
                $dropDowns[$definition['fieldTag']] = array('name' => $definition['valueListName'],
                                                            'related' => $definition['relatedFieldTag']);
            $match += $default;
            $return['input'][$definition['fieldBaseTag']][$match[2]] = $definition;
        }

        // Add the ConnectCIC 3 Transaction ID field.  Used by ConnectCIC to map prefill requests
        // to transactions.  We don't need to add this to the $return['input'] key because that's only
        // used to look up field definitions from the stage table.
        $return['hidden'][] = array(
            'fieldTag' => 'Cic3TransactionId',
            'defaultValue' => ''
        );

        $return['dropdown'] = array(
                'map' => array(),
                'type' => array()
            );
        if (!empty($dropDowns))
        {
            $types = array();
            $dropDownNames = Set::classicExtract($dropDowns, '{s}.name');
            if (($index = array_search('$_ORI_DD_$', $dropDownNames)) !== false)
            {
                array_splice($dropDownNames, $index, 1);

                $ct = ClassRegistry::init('AgencyCodeType');
                $oriType = $ct->find('first', array(
                    'fields' => array('agencyCodeTypeId'),
                    'conditions' => array(
                        'agencyId' => $this->CurrentAgency->agencyId,
                        'name' => '$_ORI_DD_$'
                    )
                ));

                if (!empty($oriType))
                    $types['$_ORI_DD_$'] = array(
                        'codeTypeId' => -$oriType['AgencyCodeType']['agencyCodeTypeId'],
                        'type' => '$_ORI_DD_$',
                        'hasRelatedType' => 0,
                        'isLargeList' => 0
                    );
            }

            $ct = ClassRegistry::init('CodeType');
            $codeTypes = $ct->find('all', array(
                'fields' => array('codeTypeId', 'type', 'hasRelatedType', 'isLargeList'),
                'conditions' => array(
                    'interface' => $this->CurrentAgency->interface,
                    'type' => $dropDownNames
                )
            ));

            $types = array_merge(Set::combine($codeTypes, '{n}.CodeType.type', '{n}.CodeType'), $types);
            $return['dropdown'] = array(
                'map' => $dropDowns,
                'type' => $types
            );
        }
        return $return;
    }

    private function populateForm($form, $template, $definition)
    {
        $html = array();
        $default = array(null, null, 1, null);

        $view = new View($this);
        $view->loadHelpers();

        $html[] = $view->element('/Forms/header', array('form' => $form, 'hidden_fields' => $definition['hidden']));

        $numericIndex = 1;
        $tooltips = array();
        $template = preg_split('/({[A-Z0-9]+(?:_F)?})/', $template, null, PREG_SPLIT_DELIM_CAPTURE);
        foreach($template as $data)
        {
            // Store the base tag in the first index, the number in the second, and the field flag in the third.
            if (!preg_match('/^{([A-Z]+)([0-9]+)?_?(F)?}$/', $data, $match))
            {
                $html[] = $data;
                continue;
            }

            $match += $default;
            $match[2] = ((!$match[2]) ? 1 : $match[2]);
            $match[3] = (($match[3] == 'F') ? true : false);

            list(,$baseTag, $fieldIndex, $isField) = $match;
            if (empty($definition['input'][$baseTag][$fieldIndex]))
            {
                // Deal with situations where the first field on the form is incorrectly associated.  In this situation
                // the first field in the definition would be LicensePlateNumber2, but the HTML form would use LIC_F
                // instead of LIC2_F.  Ultimately, this needs corrected in the form definitions.
                if ($fieldIndex == 1 && !empty($definition['input'][$baseTag]) &&
                        key($definition['input'][$baseTag]) != 1)
                    $fieldIndex = key($definition['input'][$baseTag]);
                else
                    continue;
            }

            $field = $definition['input'][$baseTag][$fieldIndex];

            if (!empty($field['refComment'])) {
                $tooltips[$field['fieldTag']] = $field['refComment'];
            }

            $element = (($isField) ? 'field' : 'label');
            $options = array('form' => $form, 'field' => $field);

            if ($element == 'field')
                $options['field_index'] = $numericIndex++;

            $html[] = $view->element("/Forms/$element", $options);
        }

        $html[] = $view->element('/Forms/footer', array('form' => $form, 'drop_downs' => $definition['dropdown'], 'tooltips' => $tooltips));
        return implode('', $html);
    }

    private function getNotes($formId)
    {
        $ds = $this->ConnectCicForm->ConnectCicFormStage->getDataSource();
        $subQuery = $ds->buildStatement(
            array(
                'fields' => array('ConnectCicFormStage.seqNum'),
                'table'  => $ds->fullTableName($this->ConnectCicForm->ConnectCicFormStage),
                'alias'  => 'ConnectCicFormStage',
                'conditions' => array(
                    'formId' => $formId,
                    'fieldLabel' => '%BEGIN_COMMENTS%'
                ),
                'limit' => null,
                'group' => null,
                'order' => null
            ),
            $this->ConnectCicForm->ConnectCicFormStage
        );

        $notes = $this->ConnectCicForm->ConnectCicFormStage->find('all', array(
            'fields'     => array('rowNum', 'fieldLabel'),
            'conditions' => array(
                'formId' => $formId,
                "seqNum > ($subQuery)",
            ),
            'order'
        ));

        if (empty($notes))
            return '';

        $lines = array();
        $lineId = $notes[0]['ConnectCicFormStage']['rowNum'];
        foreach($notes as $note)
        {
            $nextLineId = $note['ConnectCicFormStage']['rowNum'];
            for( ; $lineId < $nextLineId; ++$lineId)
                $lines[] = '';

            $lines[] = $note['ConnectCicFormStage']['fieldLabel'];
            ++$lineId;
        }

        return implode("\n", $lines);
    }

    private function wsiWrapper($request) {
        $dom = Xml::build($request, array('return' => 'domdocument'));
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $soapXml =<<<XML
<s:Envelope xmlns:s='http://schemas.xmlsoap.org/soap/envelope/' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'>
    <s:Body>
        <wsi:SendMessage xmlns:wsi='http://schema.commsys.com/xml/ConnectCic/Api/Soap'>
            <wsi:ResponseURL>http://192.168.12.126/ConnectCicResponses/submit</wsi:ResponseURL>
            <wsi:Message>
            </wsi:Message>
        </wsi:SendMessage>
    </s:Body>
</s:Envelope>
XML;
        $savedXml = substr($dom->saveXML(), 39);
        $connectCicXml = <<<XML
<api:ConnectCicApi version='3.1' xmlns:api='http://schema.commsys.com/xml/ConnectCic/Api' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'>
    <api:Route>
        <api:Source>{$this->CurrentDevice->deviceAlias}</api:Source>
        <api:Destinations>
            <api:Destination>ConnectCic</api:Destination>
        </api:Destinations>
    </api:Route>
    <api:Transactions>
        <api:Transaction id="{$request['LawEnforcementTransaction']['Transaction']['Request']['Id']}">
            {$savedXml}
        </api:Transaction>
    </api:Transactions>
</api:ConnectCicApi>
XML;
            $dom = Xml::build($connectCicXml, array('return' => 'domdocument'));
            $dom = Xml::build($soapXml, array('return' => 'domdocument'));
            $xPath = new DOMXpath($dom);
            $xPath->registerNameSpace('s','http://schemas.xmlsoap.org/soap/envelope/');
            $xPath->registerNameSpace('wsi','http://schema.commsys.com/xml/ConnectCic/Api/Soap');
            $result = $xPath->query('//wsi:Message');

            $messageNode = $result->item(0);
            $messageNode->textContent = $connectCicXml;

            $messageToSend = $dom->saveXml();

            $url = 'http://127.0.0.1:8450/ConnectCIC/StateRequest';

            // use key 'http' even if you send the request to https://...
            $options = array(
                'http' => array(
                    'header'  => "Content-type: text/xml\r\n",
                    'method'  => 'POST',
                    'content' => $messageToSend
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            if ($result === FALSE) { /* Handle error */ }
    }

    // TODO: This should be in the RequestsController.
    private function submitToConnectCic($formId=null, $values=array())
    {
        if ($formId === null)
            $formId = $this->Session->read('Form.formId');

        $form = $this->readDefinition($formId, true);

        // Find the ORI value entered by the user.
        $ori = $this->CurrentDevice->ori;
        if (!empty($this->request->data['ConnectCic']['ORI']))
            $ori = $this->request->data['ConnectCic']['ORI'];
        else if (!empty($this->request->data['ConnectCic']['OriginatingAgencyORI']))
            $ori = $this->request->data['ConnectCic']['OriginatingAgencyORI'];

        // Build the XML skeleton.
        $request = array(
            'LawEnforcementTransaction' => array(
                'Session' => array(
                    'Authentication' => array(
                        'ORI' => $ori,//$this->CurrentDevice->ori,
                        'UserAlias' => $this->CurrentUser->userAlias,
                        'DeviceAlias' => $this->CurrentDevice->deviceAlias,
                        'ClipsDeviceName' => $this->CurrentDevice->name
                    ),
                    'Id' => $this->CurrentDevice->deviceAlias
                ),
                'Transaction' => array(
                    'Request' => array(
                        'Id' => null, // Will be filled in later.
                        'MessageType' => $form['ConnectCicForm']['formName']
                    )
                )
            )
        );

        // Process the form definition.
        $line = 0;
        $concatField = null;
        $formText = array();
        $fieldText = array();
        $indicies = array();
        $requestValues = array();
        $clearLabels = array('FLD/', 'MLT/');
        $ignoreFormat = array('600', '601', '602', '603', '604', '605', '606', '607', '608', '609', '610', '611', '612',
                              '613', '614', '615', '616');
        $ignoreFormat = in_array($form['ConnectCicForm']['formName'], $ignoreFormat);
        foreach($form['ConnectCicFormStage'] as $definition)
        {
            // Process legacy command codes in the form definition, such as BEGIN_CONCAT_FIELD, END_CONCAT_FIELD, and
            // BEGIN_COMMENTS.
            if (preg_match('/%([^%]+)%(.*)/', $definition['fieldLabel'], $matches))
            {
                $matches[2] = ((empty($matches[2]))
                    ? array()
                    : array_map('trim', explode(',', $matches[2])));

                switch($matches[1])
                {
                case 'BEGIN_CONCAT_FIELD':
                    $concatField = $matches[2][0];
                    break;

                case 'END_CONCAT_FIELD':
                case 'BEGIN_COMMENTS':
                    if ($concatField)
                    {
                        $request['LawEnforcementTransaction']['Transaction']['Request'][$concatField] =
                            implode('', $fieldText);
                        $concatField = null;
                        $fieldText = array();
                    }
                    break;
                }

                if ($matches[1] == 'BEGIN_COMMENTS')
                    break;

                continue;
            }

            // Include any white space.
            while($line < $definition['rowNum'])
            {
                $formText[] = "\n";
                if ($concatField && !$ignoreFormat)
                    $fieldText[] = "\n";
                ++$line;
            }

            // Clear the label if it's one of our $clearLabels.
            if (in_array($definition['fieldLabel'], $clearLabels))
                $definition['fieldLabel'] = '';

            // Append the label to the form text and field text.
            if (!empty($definition['fieldLabel']))
            {
                $formText[] = $definition['fieldLabel'];
                if ($concatField)
                    $fieldText[] = $definition['fieldLabel'];
            }

            // If we have no field data, continue.
            if (empty($definition['fieldTag']))
                continue;

            $value = '';
            $tag = $definition['fieldTag'];
            $baseTag = $definition['fieldBaseTag'];

            // Retreive the field value. We check for (string)'0' because empty
            // will return true for it, which isn't true in this case.
            if (array_key_exists('ConnectCic', $this->request->data)
                && array_key_exists($tag, $this->request->data['ConnectCic'])
                && $this->request->data['ConnectCic'][$tag] !== '') {
                $value = (string)$this->request->data['ConnectCic'][$tag];
            }
            // Retreive the field value from the passed parameters
            else if (array_key_exists($baseTag, $values) &&
                     $values[$baseTag] != '') {
                // Iterate through the field values in the order they're read.
                if (empty($indicies[$baseTag]))
                    $indicies[$baseTag] = 0;

                $index = $indicies[$baseTag]++;
                if (array_key_exists($index, $values[$baseTag])
                    && $values[$baseTag][$index] !== '') {
                    $value = $values[$baseTag][$index];
                }
            }

            if (!empty($value) || $value === '0')
            {
                $requestValues[$tag] = $value;

                if (!empty($definition['fieldLength']) && strlen($value) > $definition['fieldLength'])
                    $value = substr($value, 0, $definition['fieldLength']);
            }

            // Store the value in the ConnectCIC message
            if ((!empty($value) || $value === '0') && $tag != 'ORI')
            {
                if ($value == '.')
                    $request['LawEnforcementTransaction']['Transaction']['Request'][$tag]['@clearValue'] = "true";
                else
                    $request['LawEnforcementTransaction']['Transaction']['Request'][$tag] = $value;
            }

            // Append the field value to our form text and field text.
            if (strtolower($definition['dataType']) != 'multilinetext')
            {
                $formText[] = str_pad($value, $definition['fieldLength']);
                if ($concatField)
                {
                    if (!$ignoreFormat)
                        $fieldText[] = str_pad($value, $definition['fieldLength']);
                    else
                        $fieldText[] = $value;
                }
            }
            else
            {
                $formText[] = $value;
                $fieldText[] = $value;
            }
        }

        if ($concatField && !empty($fieldText))
            $request['LawEnforcementTransaction']['Transaction']['Request'][$concatField] = implode('', $fieldText);

        // Generate the form text and notes.
        $formText = trim(implode('', $formText));
        $formNotes = trim($this->getNotes($form['ConnectCicForm']['formId']));

        // Include internally used/generated fields
        if (!empty($this->request->data['ConnectCic']['Cic3TransactionId']))
        {
            $cic3Id = $this->request->data['ConnectCic']['Cic3TransactionId'];
            $requestValues['Cic3TransactionId'] = $cic3Id;
            $request['LawEnforcementTransaction']['Transaction']['Request']['Cic3TransactionId'] = $cic3Id;
        }

        // Set the test indicator if it has been provided
        if (Configure::read('Interfaces.' . CurrentAgency::agency('interface') . '.enableTestIndicator'))
        {
            if ($this->CurrentUser->hasPermission('testTransactionsOnly') ||
                $this->request->data['Meta']['testForm'])
            {
                $request['LawEnforcementTransaction']['Transaction']['Request']['TestIndicator'] = 1;
                $requestValues['TestIndicator'] = '1';
            }
        }

        // Check to see if this is a form that can send to multiple destinations.
        $destinations = array();
        $adminForms = Configure::read('Interfaces.' . CurrentAgency::agency('interface') . '.adminForms');
        $broadcast = Configure::read('Clips.interface.' . CurrentAgency::agency('interface') . '.broadcast');
        $adjustValues = $requestValues;
        if (!empty($broadcast) && !empty($adminForms[$form['ConnectCicForm']['formName']]))
        {
            $adminForm = $adminForms[$form['ConnectCicForm']['formName']];
            $baseTag = $adminForm['destination'];
            for($a = 1; $a <= 6; ++$a)
            {
                $tag = $baseTag . (($a != 1) ? $a : '');
                if (!empty($adjustValues[$tag]))
                {
                    if ($adjustValues[$tag] == $broadcast['destination'])
                    {
                        $destinations = $broadcast['target'];
                        for($b = $a; $b <= 6; ++$b)
                        {
                            $c = $b + 1;
                            $target = $baseTag . (($a != 1) ? $a : '');
                            $source = $baseTag . (($c != 1) ? $c : '');
                            if (empty($adjustValues[$source]))
                                break;
                            $adjustValues[$target] = $adjustValues[$source];
                            $request['LawEnforcementTransaction']['Transaction']['Request'][$target] = $adjustValues[$source];
                            ++$a;
                        }
                        unset($adjustValues[$target]);
                        unset($request['LawEnforcementTransaction']['Transaction']['Request'][$target]);
                        if (empty($adjustValues[$adminForm['destination']]))
                        {
                            $adjustValues[$adminForm['destination']] = $destinations[0];
                            $request['LawEnforcementTransaction']['Transaction']['Request'][$adminForm['destination']] = $destinations[0];
                            array_shift($destinations);
                        }
                        break;
                    }
                }
            }
        }

        $loop = 1 + count($destinations);
        for($a = 0; $a < $loop; ++$a)
        {
            if ($a == 0)
            {
                // Build the model fields array
                $fields = array();
                foreach($requestValues as $tag => $value)
                {
                    $value = trim($value);
                    if (!empty($value) || $value === '0')
                        $fields[] = array(
                            'connectCicTag' => $tag,
                            'connectCicTagValue' => $value
                        );
                }

                if (!$this->ArchiveRequest->transaction(null, true))
                    return false;

                // Save the archive request information
                $this->ArchiveRequest->Request->clear();
                $saveData = array(
                    'Request' => array(
                        'agencyId' => $this->CurrentAgency->agencyId,
                        'requestDate' => date('Y-m-d H:i:s'),
                        'agencyDeviceId' => $this->CurrentDevice->agencyDeviceId,
                        'deviceName' => $this->CurrentDevice->name,
                        'computerName' => $this->CurrentDevice->computerName,
                        'ipAddress' => $this->request->clientIp(),
                        'connectCicMnemonic' => $this->CurrentDevice->mnemonic,
                        'connectCicORI' => $this->CurrentDevice->ori,
                        'enteredORI' => $ori,
                        'userName' => $this->CurrentUser->userName,
                        'userLastName' => $this->CurrentUser->userLastName,
                        'userFirstName' => $this->CurrentUser->userFirstName,
                        'formId' => $form['ConnectCicForm']['formId'],
                        'formTitle' => $form['ConnectCicForm']['formTitle'],
                        'connectCicMessageType' => $form['ConnectCicForm']['formName'],
                        'requestFormText' => $formText,
                        'requestFormNotes' => $formNotes,
                        'requestIsNew' => true
                    ),
                    'RequestValue' => $fields,
                );

                $this->ArchiveRequest->Request->saveRequest($saveData, array('atomic' => false));
                // Update Session table to reflect latest transaction.
                $updateRequest = ClassRegistry::init('UserSession');
                $updateRequest->updateActivity($this->Session->id(), array(
                    'ipAddress' => $saveData['Request']['ipAddress'],
                    'agencyId' => $saveData['Request']['agencyId'],
                    'agencyUserId' => $this->CurrentUser->agencyUserId,
                    'agencyDeviceId' => $saveData['Request']['agencyDeviceId'],
                    'lastTransaction' => $saveData['Request']['connectCicMessageType'] . ' ' . $saveData['Request']['formTitle']
                ));


                if ($this->Mobile->isMobile() == true)
                    $this->CurrentRequest->requestId($this->ArchiveRequest->id);

                // Store the Request ID in the XML
                $request['LawEnforcementTransaction']['Transaction']['Request']['Id'] = $this->ArchiveRequest->id;
            }
            else
            {
                for($b = 1; $b <= 6; ++$b)
                {
                    $tag = $baseTag . (($b != 1) ? $b : '');
                    unset($request['LawEnforcementTransaction']['Transaction']['Request'][$tag]);
                }
                $request['LawEnforcementTransaction']['Transaction']['Request'][$baseTag] = $destinations[$a - 1];
            }

            // Generate the XML to submit to ConnectCIC
            $this->wsiWrapper($request);
            // $dom = Xml::build($request, array('return' => 'domdocument'));
            // $dom->preserveWhiteSpace = false;
            // $dom->formatOutput = true;
            // Store the request in the message queue
            //$this->MessageQueue->clear();
            // $this->MessageQueue->set(array(
            //     'mailbox' => $this->CurrentDevice->deviceAlias,
            //     'message' => $dom->saveXml(),
            //     'processState' => 'Queued',
            //     'createDate' => date('Y-m-d H:i:s'),
            // ));

            // if (!$this->MessageQueue->save())
            //     return $this->ArchiveRequest->transaction(false, true);
        }

        if ($this->CriminalHistorySession->inSession() &&
            // We have to explicitly pass the interface and form here because command line transactions don't change
            // the selected form.  Otherwise, we run into issues like #1497
            $this->CriminalHistorySession->isCriminalHistoryForm($this->CurrentAgency->interface,
                                                                 $form['ConnectCicForm']['formName']))
        {
            if (!$this->CriminalHistorySession->appendRequest($this->ArchiveRequest->id))
                return $this->ArchiveRequest->transaction(false, true);

            $this->CriminalHistorySession->approve(false);
        }

        if (!$this->ArchiveRequest->transaction(true, true))
            return false;

        return $this->ArchiveRequest->id;
    }
}
