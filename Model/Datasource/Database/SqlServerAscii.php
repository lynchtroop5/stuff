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
App::uses('Profiler', 'Lib');
App::uses('Sqlserver', 'Model/Datasource/Database');

// CakePHP's default SQL Server implementation quotes string values by prefixing them with N, making them a unicode
// value.  This results in SQL Server promoting the data stored in the database, which is ASCII, to unicode.  Since
// this operation changes the original stored value, SQL server must execute a table scan across all rows to do any
// WHERE clause evaluation.  This makes all our indexes on the tables useless.
//
// To fix this, we created a custom SQL Server driver which replaces the default quoting function to remove the N from
// the quoting result.  This makes SQL Server treat the value as ASCII, allowing SQL Server to utilize our indexes.
class SqlServerAscii extends Sqlserver
{
    public function _execute($sql, $params = array(), $prepareOptions = array())
    {
        if (Profiler::enabled())
            CakeEventManager::instance()->dispatch(
                new CakeEvent('Datasource.beforeSql', $this, array(
                        'sql' => $sql, 
                        'params' => $params, 
                        'options' => $prepareOptions)));
        
        $result = parent::_execute($sql, $params, $prepareOptions);
        
        if (Profiler::enabled())
            CakeEventManager::instance()->dispatch(
                new CakeEvent('Datasource.afterSql', $this, array(
                        'sql' => $sql, 
                        'params' => $params, 
                        'options' => $prepareOptions)));

        return $result;
    }

	public function value($data, $column=null, $null=true)
	{
		$result = parent::value($data, $column, $null);
		if (is_array($result))
		{
			foreach($result as $index => $value)
			{
				if (is_string($value) && strlen($value) >= 3 && $value[0] == 'N' && $value[1] == '\'')
					$result[$index] = substr($value, 1);
			}
		}
		else if (is_string($result) && strlen($result) >= 3 && $result[0] == 'N' && $result[1] == '\'')
			$result = substr($result, 1);

		return $result;
	}
}
