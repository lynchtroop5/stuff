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
App::uses('ClipsConfiguration', 'Lib');
App::uses('DataSource', 'Model/Datasource');

// Adapts the array from ClipsConfiguration to a data source object that can
// be read/manipulated by the ConfigFile model.
class ConfigurationSource extends DataSource
{
    private $data = null;
    private $changed = false;
    private $lock = null;

    public $_schema = array(
        'config' => array(
            // This is here because the model will filter this out when trying to save otherwise
            'agencyId' => array(
                'type' => 'integer',
                'path' => '',
                'description' => 'Agency ID for agency specific configuration values.'
            )
        )
    );

    public function __construct($config=array())
    {
        // Merge in the schema from the ClipsConfiguration.
        $this->_schema = Hash::merge($this->_schema, array('config' => ClipsConfiguration::schema()));

        parent::__construct($config);
    }
    

    public function connect()
    {
        $this->connected = true;
        return true;
    }
    
    public function close()
    {
        $this->connected = false;
        return true;
    }
    
    public function listSources($data=null)
    {
        return array('config');
    }

    public function describe($model)
    {
        return $this->_schema['config'];
    }
    
    // $queryData supports only the following parameters:
    //
    // $queryData = array(
    //   'fields' => array('field1', 'field2', ...),
    //   'conditions' => array(
    //     'agencyId' => 0
    //   )
    // )
    public function read(Model $model, $queryData=array(), $recursive=null)
    {
        $fields = array();
        if (!empty($queryData['fields']))
            $fields = $queryData['fields'];

        $agencyId = 0;
        if (!empty($queryData['conditions']['agencyId']))
            $agencyId = $queryData['conditions']['agencyId'];

        $config = ClipsConfiguration::read($agencyId);
        $config = ClipsConfiguration::toModel($config, $fields);

        return array($model->alias => $config);
    }

    // Since there are no primary keys/ids, all model save calls should end up delegating to create.
    public function create(Model $model, $fields=null, $values=null)
    {
        $agencyId = 0;
        $values = array_combine($fields, $values);

        if (!empty($values['agencyId']))
        {
            $agencyId = $values['agencyId'];
            unset($values['agencyId']);
        }

        $new = ClipsConfiguration::fromModel($values);
        
        // If no agency, just write the system file
        if ($agencyId == 0)
        {
            $config = ClipsConfiguration::read();
            $config = Hash::merge($config, $new);

            ClipsConfiguration::writeSystemFile($config);
            return true;
        }

        // We have an agnecy, needs to be saved to the database instead
        $data = array();
        $paths = ClipsConfiguration::toPaths($new);

        array_walk($paths, function($value, $path) use(&$data, $agencyId) {
                $data[] = array('agencyId' => $agencyId, 'path' => $path, 'value' => $value);
            });

        $am = ClassRegistry::init('AgencyConfiguration');

        // Delete the current configuration values
        if (!$am->deleteAll(array('AgencyConfiguration.agencyId' => $agencyId)))
        {
            $am->getDataSource()->rollback();
            throw new ModelException('Failed to remove current agency configuration');
        }

        // Write the new configuration parameters
        if (!$am->saveMany($data, array('atomic' => false)))
        {
            $am->getDataSource()->rollback();
            throw new ModelException('Failed to save new agency configuration');
        }

        $am->getDataSource()->commit();
        return true;
    }

    private function flush()
    {
        return true;
    }
    
    private function lock()
    {
        return true;
    }
    
    private function unlock()
    {
        return true;
    }
}
