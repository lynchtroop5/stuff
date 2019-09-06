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

// Represents a command alias which can be used by users on the CLIPS command line.
class Binding extends AppModel
{
	public $useTable     = 'tblBinding';
	public $primaryKey   = 'bindingId';

	public $validate = array(
		'interface' => array(
			'rule' => '/^[A-Z]{2}_[_A-Z]+$/',
			'message' => 'You must specify a valid ConnectCIC formatted interface name.'
		),
		'bindName' => array(
			'formatted' => array(
				'rule' => 'notBlank',
				'message' => 'Bind Name must be specified.'
			),
			'unique' => array(
				// bindName must be unique within a given interface
				'rule' => array('unique', 'interface'),
				'message' => 'The bind name is already in use.'
			)
		),array(
			'rule' => 'boolean',
			'message' => 'Legacy must have a value of \'0\', no, or \'1\', yes'
		),
		'command' => array(
			'rule' => 'notBlank',
			'message' => 'Command must be specified.'
		)
	);
}
