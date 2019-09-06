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

class CommandLinesController extends AppController
{
    public $uses = array('ConnectCicForm', 'Binding');
    public $components = array('CriminalHistorySession');

    private $parseStack=array();
    
    public function issue()
    {
        if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}

		if (!empty($this->request->data['CommandLine']['command'])) 
		{
			$result = $this->processCommand($this->request->data['CommandLine']['command']);
			if ($result === true) {
				exit;
			}
			
			if (is_array($result)) {
				$result = Router::url($result);
			}

			if (is_string($result)) 
			{
				if (!$this->request->is('ajax')) {
					return $this->redirect($result);
				}
				$this->set('redirect', $result);
				$this->set('_serialize', array('redirect'));
			}
		}
    }
    
    private function processCommand($cmd) 
    {
        $bDone = FALSE;
        $bTryBindings = FALSE;

        // Looping because we first try a command, then trandaction, then bindings.
        // Command and Bindings were originally separated with a period, but that has been removed.
        // Since the processToCommandArray took into account bindings automatically (checked for period)
        // we now have to send it a flag to ask it to check bindings.  First we try command and transaction.
        // If that failed, we reparse the command line taking into account bindings.  We'll either get
        // back a new command or the same one.  If we get the same one, we quit. If we get a new one,
        // then it's a resulting command from a bind (or recursive list of binds that has already been 
        // handled by the parser).  Check the new command against our parsing.
        while(!$bDone)
        {
            $results = Array();
            $bIsCommand = $this->processToCommandArray($cmd, $results, $bTryBindings);

            if ($bIsCommand == FALSE && $results == NULL)
                return;
            
            // Try processing this as a CLIPS command
            $fn = 'handleCommand_' . $results[0]['value'];
            if (method_exists($this, $fn))
            {
                $this->$fn($results);
                return;
            }

            $values = Array();
            $totalParams = sizeof($results);

            // If they typed one paramter, use it as a search string
            // to find a form.
            if ($totalParams == 1) {
                return array(
                    'controller' => 'forms',
                    'Filter[FormList][sectionId]' => 0,
					'Filter[FormList][text]' => $results[0]['value']);
			}

            // Don't allow criminal history forms to be run by command line.
            if ($this->CriminalHistorySession->isCriminalHistoryForm(
                    $this->CurrentAgency->interface,
                    $results[0]['value']))
                return $this->referer();

            // We'll let the forms controller deal with user permissions.
            if ($this->ConnectCicForm->hasAny(array(
                'interface' => $this->CurrentAgency->interface, 
                'formName' => $results[0]['value'])))
            {
                for($a = 1; $a < $totalParams; ++$a)
                {
                    $param = $results[$a];

                    if ($param['tag'] == '')
                        $param['tag'] = 'FLD';

                    if (!array_key_exists($param['tag'], $values))
                        $values[$param['tag']] = Array();

                    $values[$param['tag']][] = $param['value'];
                }

                $this->requestAction(
                    array(
                        'controller' => 'forms',
                        'action' => 'submit'
                    ), 
                    array(
                        'pass' => array($results[0]['value']),
                        'named' => array(
                            'CommandLine' => array('values' => $values)
                        )
                    )
                );
                return true;
            }

            if ($bTryBindings == FALSE)
                $bTryBindings = TRUE;
            else 
                $bDone = TRUE;
        }
    }
    
	// CLIPS Unbind command.
	// USE: .UNBIND.<ALIAS>
	private function handleCommand_Unbind($cmdArray)
	{
		if (!is_array($cmdArray))
			return;

		if (sizeof($cmdArray) != 2)
			return;

		$what = $cmdArray[1]['value'];	// Get alias to unbind
        $this->Binding->deleteAll(array(
            'interface' => $this->CurrentAgency->interface,
            'bindName' => $what
        ));
	}
	
	// Parses the passed command string into an array structure.
	// and returns it.
	private function parseCommand($cmd)
	{
		if (!$cmd)
			return NULL;
	
		$i = 0;
		$nStart = 0;
		$state = 0;
		$bIsCommand = FALSE;
		$paramArr = Array();
		$paramBase = Array
		(
			'tag' => '',
			'value' => '',
			'isQuoted' => FALSE,
		);
	
		$cmdLen = strlen($cmd);
	
		$param = $paramBase;
		$escapeChars = Array('"', '\\', '.', '$');

		while($i < $cmdLen)
		{
			switch($state)
			{
			case 0:
				if ($cmd[$i] == '.')
				{					
					$paramArr[] = $param;
					$param = $paramBase;
					$nStart = $i;
				}
				else if ($cmd[$i] == '"')
				{
					if ($param['value'] == '')
					{
						$param['isQuoted'] = TRUE;
						$state = 2;
					}
					else
						$param['value'].= $cmd[$i];
				}
				else if ($cmd[$i] == '\\')
				{
					$state = 1;
				}
				else if ($cmd[$i] == '/' && $param['tag'] == '')
				{
					if ($param['value'] == '' || strlen($param['value']) != 3)
						$param['value'].= '/';
					else
					{
						$param['tag'] = $param['value'];
						$param['value'] = '';
					}
				}
				else
					$param['value'].= $cmd[$i];
	
				++$i;
				break;
	
			case 1:
				if (in_array($cmd[$i], $escapeChars, TRUE))
					$param['value'].= $cmd[$i];
				else 
				{
					$param['value'].= '\\';
					$param['value'].= $cmd[$i];
				}
	
				$state = 0;
				++$i;
				break;
	
			case 2:
				if ($cmd[$i] == '"')
					$state = 0;
				else if ($cmd[$i] == '\\')
					$state = 3;
				else
					$param['value'].= $cmd[$i];
	
				++$i;
				break;
	
			case 3:
				if (in_array($cmd[$i], $escapeChars, TRUE))
					$param['value'].= $cmd[$i];
				else 
				{
					//print_r($param);
					$param['value'].= '\\';
					$param['value'].= $cmd[$i];
				}

				$state = 2;
				++$i;
				break;
			}
		}

		if ($param['value'] != '')
			$paramArr[] = $param;

		//echo 'In ParseCommand<pre>';
		//print_r($paramArr);
		//echo '</pre>';
			
		return $paramArr;
	}

	private function escapeValue($value)
	{
		$rep = Array
		(
			'\\' => '\\\\',
			'"' => '\\"',
			'.' => '\\.'
		);
	
		return str_replace(array_keys($rep), array_values($rep), $value);
	}
	
	private function getParamString($param)
	{
		if (!$param)
			return '';
	
		$str = '';
		if ($param['tag'])
			$str.= $this->escapeValue($param['tag']) . '/';
	
		if ($param['isQuoted'])
			$str.= '"' . $this->escapeValue($param['value']) . '"';
		else
			$str .= $this->escapeValue($param['value']);
	
		return $str;
	}
	
	private function getForwardParamString($paramArr, $startIndex)
	{
		$vals = Array();
		$totalParams = sizeof($paramArr);
		for($a = $startIndex; $a < $totalParams; ++$a)
			$vals[] = $this->getParamString($paramArr[$a]);
		return implode('.', $vals);
	}

	private function getBoundCommand($name)
	{
        $results = $this->Binding->find('first', array(
            'fields' => array('command'),
            'conditions' => array(
                'interface' => $this->CurrentAgency->interface,
                'bindName' => $name
            )
        ));
        
        return $results['Binding']['command'];
	}

	// Parses the passed command.  Returns the structure of the 
	// command through $resultArr and returns TRUE if the command
	// is a CLIPS command, FALSE if it should be treatd as a 
	// transaction.
	//
	// $resultArr is NULL if there was an error and FALSE is returned.
	//
	// Bindings are taken into account.
	private function processToCommandArray($cmd, &$resultArr, $bForceCommand=FALSE)
	{
		$r = Array();
		$bIsCommand = FALSE;
		$cmd = strtoupper(trim($cmd));

		$this->finalCommand = $cmd;
		
		if (!$cmd)
		{
			$resultArr = NULL;
			return FALSE;
		}
		
		//if ($cmd[0] == '.')
		//{
		//	$bIsCommand = TRUE;
		//	$cmd = substr($cmd, 1);
		//}
		//else 
		//{
			//$this->errorStr = (($bForceCommand) ? 'YES!' : 'NO!');
			$bIsCommand = $bForceCommand;
		//}

		$resultArr = $this->parseCommand($cmd);

		//echo 'In ProcesssToCommandArray <pre>';
		//print_r($resultArr);
		//echo '</pre>';
		
		if (!$bIsCommand)
			return FALSE;

		$cmdType = $resultArr[0]['value'];
		if (($bindCmd = $this->getBoundCommand($cmdType)))
		{
			// Handle recursion checking
			if (in_array($cmdType, $this->parseStack, TRUE))
			{
				$resultArr = NULL;
				return FALSE;
			}

			array_push($this->parseStack, $cmdType);

			$bBoundIsCommand = FALSE;
			$cmd = $bindCmd;

			if ($bindCmd[0] == '.')
			{
				$bindCmd = substr($bindCmd, 1);
				$bBoundIsCommand = TRUE;
			}

			$bindArr = $this->parseCommand($bindCmd);
			if (!$bindArr)
			{
				$resultArr = NULL;
				return FALSE;
			}
			
			//print_r($bindArr);

			$newStr = Array();
			foreach($bindArr as $idx => $param)
			{
				//echo 'Processing Param<pre>';
				//print_r($param);
				//echo '</pre>';
				if (($paramStart = strpos($param['value'], '$')) === FALSE)
				{
					$newParams[] = $this->getParamString($param);
					continue;
				}

				++$paramStart;

				// Find the end of the parameter
				$paramEnd = $paramStart;
				$len = strlen($param['value']);
				for($a = $paramStart; $a < $len; ++$a, $paramEnd++)
				{
					if (!is_numeric($param['value'][$a]))
					{
						if ($param['value'][$a] == '-')
							++$paramEnd;
						break;
					}
				}

				$index = substr($param['value'], $paramStart, $paramEnd - $paramStart);
				if ($index == '')
					continue;

				if (substr($index, -1) == '-')
				{
					$index = substr($index, 0, -1);
					$value = substr($param['value'], 0, $paramStart -1)
						. $this->getForwardParamString($resultArr, $index - 1)
						. substr($param['value'], $paramEnd, $len - $paramEnd);
					if ($param['isQuoted'] == TRUE)
						$value = '"' . str_replace(Array('\\', '"'), Array('\\\\', '\\"'), $value) . '"';
				}
				else
				{
					$value = '';
					if (array_key_exists($index - 1, $resultArr))
						$value = substr($param['value'], 0, $paramStart -1)
							. $this->getParamString($resultArr[$index - 1])
							. substr($param['value'], $paramEnd, $len - $paramEnd);
				}

				//echo 'Value: ' . $value . '<br />';
				
				if ($value == '')
					continue;

				if ($param['tag'] != NULL)
					$newParams[] = $param['tag'] . '/' . $value;
				else
					$newParams[] = $value;
			}

			$cmd = '';
			if ($bBoundIsCommand == TRUE)
				$cmd = '.';

			$cmd.= implode('.', $newParams);
			return $this->processToCommandArray($cmd, $resultArr);
		}

		return TRUE;
	}
}
