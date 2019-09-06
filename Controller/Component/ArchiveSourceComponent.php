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
App::uses('Component', 'Controller');

class ArchiveSourceComponent extends Component
{
    public function getSources()
    {
        // Create a temporary datasource on the fly
        $ds = ConnectionManager::create('master', array(
                'datasource' => 'Database/SqlServerAscii',
                'persistent' => false,
                'host' => Configure::read('Clips.database.website.host'),
                'login' => Configure::read('Clips.database.website.user'),
                'password' => Configure::read('Clips.database.website.password'),
                'database' => 'master',
                'schema' => '',
                'prefix' => ''
            ));

        // Query for a list of CLIPS archive tables
        $sql = 'select name ' .
               'from sys.databases ' .
               'where name like \'CLIPS_20[0-9][0-9][0-1][0-9][0-3][0-9]\' ' .
               'order by name desc';
        $result = $ds->fetchAll($sql);
        unset($ds);

        ConnectionManager::drop('master');
        
        $result = (!empty($result) 
            ? Set::classicExtract($result, '{n}.0.name')
            : array());

        // Strip the 'CLIPS_' from the database name
        $sources = array();
        foreach($result as $source)
        {
            $date = substr($source, 6);
            $sources[$date] = $date;
        }
        
        return $sources;
    }

    public function setSource($source, $model)
    {
        // Create a temporary datasource on the fly
        $ds = ConnectionManager::create('source', array(
                'datasource' => 'Database/SqlServerAscii',
                'persistent' => false,
                'host' => Configure::read('Clips.database.website.host'),
                'login' => Configure::read('Clips.database.website.user'),
                'password' => Configure::read('Clips.database.website.password'),
                'database' => $source,
                'schema' => '',
                'prefix' => ''
            ));
        $model->useDbConfig = 'source';
    }
}
