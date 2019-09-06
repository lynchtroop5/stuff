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

// Represents a single CLIPS configuration value for a given agency.
class AgencyConfiguration extends AppModel
{
    public $useTable = 'tblAgencyConfiguration';
    public $primaryKey = 'configurationId';

    public $belongsTo = array(
            'Agency' => array(
                    'foreignKey' => 'agencyId'
            )
    );
    public $validate = array(
            'agencyId' => array(
                    'rule' => array('rowExists', 'Agency.agencyId'),
                    'message' => 'Agency ID must be the ID of an existing agency.'
            ),
            'path' => array(
                    'rule' => 'notBlank',
                    'message' => 'Path may not be empty.'
            )
    );

    /*
    public function findAgencyConfiguration($agencyId, $fields=array())
    {
        $configFile = ClassRegistry::init('ConfigFile');
        return $configFile->find('all', array(
            'fields' => $fields,
            'conditions' => array(
                'agencyId' => $agencyId
            )
        ));
    }
    */

    /*
    public function saveAgencyConfiguration($agencyId, $data, $options=array())
    {
        // Use the ConfigFile model to convert the posted data into an array of path/value pairs, which is stored in
        // the Configuration model.  This is because that model's data source knows how to perform the conversions.
        $configFile = ClassRegistry::init('ConfigFile');
        $data = $configFile->convertToPaths($data);
        
        $options = array_merge(array('atomic' => 'true'), $options);
        $this->transaction(null, $options['atomic']);

        if (!$this->deleteAll(array('Configuration.agencyId' => $agencyId)))
            return $this->transaction(false, $options['atomic']);

        foreach($data as $key => $value)
        {
            $this->clear();
            $this->set(array_merge($value, array('agencyId' => $agencyId)));

            if (!$this->save())
                return $this->transaction(false, $options['atomic']);
        }

        return $this->transaction(true, $options['atomic']);
    }
     */
}
