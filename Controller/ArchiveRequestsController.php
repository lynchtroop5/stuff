<?php

/* * *****************************************************************************
 * Copyright 2012 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 * **************************************************************************** */

class ArchiveRequestsController extends AppController {

    public $components = array('ArchiveSource', 'CurrentRequest', 
        'Filter' => array(
            'datetime' => 'requestDate',
            'fields' => array(
                'source', 
                'deviceName' => array('comparison' => 'like'),
                'connectCicORI' => array('comparison' => 'like'), 
                'connectCicMnemonic' => array('comparison' => 'like'),
                'user', // Handled in _adjustFilter
                'formSectionId',
                'form',// Handled in _adjustFilter
                'text' // Handled in _adjustFilter
            )
        )
    );

    public function index() {
        if ($this->Filter->changed()) {
            $description = $this->Filter->description();
            AuditLog::Log(AUDIT_RQ_SEARCH, $description);
        }

        // TODO: Make this work
        $fs = ClassRegistry::init('FormSection');
        $formSections = $fs->find('list', array(
            'conditions' => array(
                'interface' => $this->CurrentAgency->interface
            ),
            'order' => 'sectionName'
                ));

        $this->set('form_sections', $formSections);
        $this->set('sources', $this->ArchiveSource->getSources());
        $this->set('requests', $this->getFilteredResults(25));
    }

    public function print_results() {
        $this->layout = 'print';
        $this->set('requests', $this->getFilteredResults());
    }

    public function print_request($requestId, $responseId = null) {
        $this->layout = 'print';

        if ($responseId == null && !empty($this->request->params['named']['ids']))
            $responseId = explode(',', $this->request->params['named']['ids']);

        if ($requestId === '0' && !empty($responseId)) {
            $data = $this->ArchiveRequest->getGeneralResponse($responseId);
        } else {
            $this->relinkConnectCicRequest();
            $data = $this->ArchiveRequest->readFullRequest($requestId, $responseId);
        }
        $this->set('request', $data);
    }

    public function restore($requestId) {
        if ($requestId == 0)
            return $this->redirect($this->referer());

        $this->ArchiveRequest->restoreFromArchive(
                $this->CurrentDevice->agencyDeviceId, $requestId);

        $this->CurrentRequest->requestId($requestId);
        return $this->redirect(array('controller' => 'responses'));
    }

    public function _adjustFilter($key, $model, $filter, $conditions) {
        // Handle Form Group
        if (!empty($conditions['ArchiveRequest.formSectionId'])) {
            $formSectionId = $conditions['ArchiveRequest.formSectionId'];
            unset($conditions['ArchiveRequest.formSectionId']);

            $conditions[] = 'ArchiveRequest.formId in (select formId ' .
                    'from tblFormSectionForm ' .
                    'where formSectionId = ' . (int) $formSectionId . ')';
        }

        // Handle User
        if (!empty($conditions['ArchiveRequest.user'])) {
            $user = FilterComponent::fromMask($conditions['ArchiveRequest.user']);
            unset($conditions['ArchiveRequest.user']);

            $conditions['AND'][] = array(
                array(
                    'OR' => array(
                        'ArchiveRequest.userName like' => $user,
                        'ArchiveRequest.userFullNameLast like' => $user
                    )
                )
            );
        }

        if (!empty($conditions['ArchiveRequest.form'])) {
            $form = FilterComponent::fromMask($conditions['ArchiveRequest.form']);
            unset($conditions['ArchiveRequest.form']);

            $conditions['AND'][] = array(
                array(
                    'OR' => array(
                        'ConnectCicForm.formName like' => $form,
                        'ConnectCicForm.formMessageKey like' => $form,
                        'ConnectCicForm.formTitle like' => $form,
                    )
                )
            );
        }

        if (!empty($conditions['ArchiveRequest.text'])) {
            $text = FilterComponent::fromMask($conditions['ArchiveRequest.text']);
            unset($conditions['ArchiveRequest.text']);

            $conditions['AND'][] = 
                'ArchiveRequest.requestId in(select requestId ' .
                                            'from tblArchiveRequestValue ' .
                                            'where requestId = ArchiveRequest.requestId ' .
                                                'and connectCicTagValue like ' . 
                                                    $model->getDataSource()->value($text) . ')';
        }

        // Force the device name if the user doesn't have appropriate permissions.
        if (!$this->CurrentUser->hasPermission('searchRequestHistory'))
            $conditions['ArchiveRequest.deviceName'] = $this->CurrentDevice->name;

        // Handle the source
        if (!empty($filter['source']))
        {
            unset($conditions['ArchiveRequest.source']);
            $source = "CLIPS_" . $filter['source'];
            $this->ArchiveSource->setSource($source, $model);
        }

        return $conditions;
    }

    private function relinkConnectCicRequest() {
        $this->ArchiveRequest->unbindModel(array('hasMany' => array('ArchiveConnectCicRequest')));
        $this->ArchiveRequest->bindModel(array(
            'hasOne' => array(
                'ArchiveConnectCicRequest' => array(
                    'foreignKey' => false,
                    'conditions' => array(
                        'connectCicRequestId = (select top 1 connectCicRequestId' .
                        ' from tblArchiveConnectCicRequest' .
                        ' where requestId = ' . $this->ArchiveRequest->alias . '.requestId' .
                        ' order by connectCicRequestId)'
                    ),
                    'type' => 'LEFT'
                )
            )
        ));
    }

    private function getFilteredResults($limit = 0) {
        // Re-Map the ConnectCicRequest information to contain only the first record.
        $this->relinkConnectCicRequest();
        $options = array(
            'contain' => array(
                'ConnectCicForm' => array(
                    'fields' => array('formName', 'formTitle')
                ),
                'ArchiveConnectCicRequest' => array(
                    'fields' => array('requestIdentifier', 'requestDescription', 'requestInfo')
                )
            ),
            'fields' => array('requestId', 'requestDate', 'deviceName', 'computerName', 'ipAddress', 'userName',
                'userFullNameLast', 'formTitle', 'connectCicORI', 'connectCicMnemonic', 'formId'),
            'conditions' => array('ArchiveRequest.agencyId' => $this->CurrentAgency->agencyId),
            'order' => array('requestDate' => 'desc'),
            'paginate' => ($limit > 0)
        );

        if ($limit > 0)
            $options['limit'] = $limit;
        else if (!empty($this->request->params['named']['sort'])) {
            $options['order'] = $this->request->params['named']['sort'];
            if (!empty($this->request->params['named']['direction']))
                $options['order'] .= ' ' . $this->request->params['named']['direction'];
        }

        return $this->Filter->filter($options);
    }

}
