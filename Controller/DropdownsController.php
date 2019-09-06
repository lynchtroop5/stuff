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

class DropdownsController extends AppController
{
    var $uses = array('CodeType', 'AgencyCodeType');
    var $components = array('RequestHandler');

    public function view($id)
    {
        if (empty($this->request->params['ext']))
            return;
        
        $search = (($id < 0) 
            // Search for agency specific drop down values
            ? array('model' => $this->AgencyCodeType->AgencyCode, 
                    'fields' => array('c.code as v', 'c.description as d'), 
                    'conditions' => array('c.agencyCodeTypeId' => -$id))

            // search for global drop down values
            : array('model' => $this->CodeType->Code, 
                    'fields' => array('c.code as v', 'c.codeValue as d'), 
                    'conditions' => array('c.codeTypeId' => $id)));
        
        $ds = $this->CodeType->Code->getDataSource();
        $sql = $ds->buildStatement(array(
            'fields' => $search['fields'],
            'table'  => $ds->fullTableName($search['model']),
            'alias'  => 'c',
            'limit'  => null,
            'joins'  => array(),
            'conditions' => $search['conditions'],
            'group' => null,
            'order' => null
        ), $search['model']);
        
        // HACK: This doesn't pay attention to the Drop Down type and assumes it's an ORI drop down.
        if ($id < 0)
            $sql.= ' union select ' . $ds->value($this->CurrentDevice->ori) . ', \'This device ORI.\' '
                 . ' union select ' . $ds->value(Configure::Read('Clips.agency.agencyOri')) . ', \'This agency ORI.\' ';
        
        $sql.= ' for xml auto, elements';

        $conn = $ds->getConnection();
        $result = $conn->query($sql);
        $result = $result->fetchAll(PDO::FETCH_COLUMN);

        $this->set('codes', $result);
    }
}
