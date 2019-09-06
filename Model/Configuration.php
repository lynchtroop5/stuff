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
App::uses('Ldap', 'Lib');

class Configuration extends AppModel
{
    public $useTable = 'config';
    public $useDbConfig  = 'config';

    public $validate = array(
        // CLIPS Database
        'clipsDatabase'        => array('rule' => 'notBlank'),
        'clipsUser'            => array('rule' => 'notBlank'),
        'clipsPassword' => array(
            'rule' => array('matches', 'clipsConfirmPassword')
        ),
        'clipsHost' => array(
            'present' => array(
                'rule' => 'notBlank'
            ),
            'connects' => array(
                'rule' => array(
                    'verifyDatabase', 
                    'clipsUser', 
                    'clipsPassword',
                    'clipsDatabase'
                ),
            )
        ),
        
        // ConnectCIC Database
        'connectCicDatabase'        => array('rule' => 'notBlank'),
        'connectCicUser'            => array('rule' => 'notBlank'),
        'connectCicPassword' => array(
            'rule' => array('matches', 'connectCicConfirmPassword')
        ),
        'connectCicHost' => array(
            'present' => array(
                'rule' => 'notBlank'
            ),
            'connects' => array(
                'rule' => array(
                    'verifyDatabase', 
                    'connectCicUser', 
                    'connectCicPassword',
                    'connectCicDatabase'
                ),
            )
        ),
        'agencyOri' => array(
            'rule' => '/[A-Z][A-Z][0-9A-Z]{7}/i',
            'message' => 'The ORI must be 9 characters and start with a two character state code.'
        ),
        /*
        'authType' => array(
            'rule' =>  array('verifyLdap', 'authLdapServerUrl', 'authLdapServerPort', 'authLdapFailoverUrl', 
                            'authLdapFailoverUrl', 'authLdapFailoverPort', 'authLdapProtocol', 'authLdapReferrals',
                            'authLdapBindAccount', 'authLdapBindPassword', 'authLdapConfirmBindPassword', 
                            'authLdapRoot', 'authLdapFilter', 'authLdapUserField'),
        ),
        'authLdapServerUrl' => array('rule' => array('ldapValueNotEmpty', 'authType')),
        'authLdapServerPort' => array('rule' => array('ldapValueNotEmpty', 'authType')),
        'authLdapProtocol' => array('rule' => array('ldapValueNotEmpty', 'authType')),
        'authLdapReferrals' => array('rule' => array('ldapValueNotEmpty', 'authType')),
        'authLdapRoot' => array('rule' => array('ldapValueNotEmpty', 'authType')),
        'authLdapUserField' => array('rule' => array('ldapValueNotEmpty', 'authType')),
        */
        'idlePeriod' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Idle period must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('comparison', '>=', 0),
                'message' => 'Idle period must be greater than or equal to 0.'
            )
        ),
        'criminalHistoryRange' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Criminal History Range must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('comparison', '>=', 0),
                'message' => 'Criminal History Range must be greater than or equal to 0.'
            )
        ),
        'requestHistoryRange' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Request History Range must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('comparison', '>=', 0),
                'message' => 'Request History Range must be greater than or equal to 0.'
            )
        ),
        'responseHistoryRange' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Response History Range must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('comparison', '>=', 0),
                'message' => 'Response History Range must be greater than or equal to 0.'
            )
        ),
        'securityLogRange' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Security Log Range must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('comparison', '>=', 0),
                'message' => 'Security Log Range must be greater than or equal to 0.'
            )
        ),
        'maxLoginAttempts' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Max login attempts must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('comparison', '>=', 0),
                'message' => 'Max login attempts must be greater than or equal to 0.'
            )
        ),
        'passwordMinimumLength' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Password minimum length must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('comparison', '>=', 0),
                'message' => 'Password minimum length must be greater than or equal to 0.'
            )
        ),
        'passwordHistory' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Password history must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('comparison', '>=', 0),
                'message' => 'Password history must be greater than or equal to 0.'
            )
        ),
        'passwordExpires' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Password expiration must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('comparison', '>=', 0),
                'message' => 'Password expiration must be greater than or equal to 0.'
            )
        ),
        'passwordSimilarity' => array(
            'number' => array(
                'rule' => 'numeric',
                'message' => 'Password similarity must be a numeric value.'
            ),
            'range' => array(
                'rule' => array('range', -1, 101),
                'message' => 'Password similarity must be between 0 and 100 percent.'
            )
        )
    );

    public function matches($check, $target)
    {
        $source = key($check);
        $value = current($check);
        $compare = $this->data[$this->alias][$target];
        
        if ($value != $compare)
        {
            $this->invalidate($source, 'Value does not match');
            $this->invalidate($target, 'Value does not match');
        }

        return true;
    }
    
    // Verify database connectivity.  This actually does the validation for all database related fields.  It returns
    // true in most cases to prevent an unnecessary error message from appearing under the host field.
    public function verifyDatabase($check, $user, $password, $database)
    {
        $host = key($check);
        $fields = compact('host', 'user', 'password', 'database');
        
        // If there are any validation errors on the other fields, just abort. We can't connect unless those are valid.
        foreach($fields as $field)
        {
            if (!empty($this->validationErrors[$field]))
                return true;
        }
        
        // Create the key/value pairs for the database connection.
        $data = array_intersect_key($this->data[$this->alias], 
                array_flip($fields));
        
        // If no password was supplied, that means the user didn't change it. Use the congiured one.
        if (empty($data[$password]))
            $data[$password] = ClipsConfiguration::configValue($password);

        // Attempt to create the datasource.  If it throws, the connection failed.
        try
        {
            $ds = new SqlServerAscii(array(
                    'persistent' => false,
                    'host' => $data[$host],
                    'login' => $data[$user],
                    'password' => $data[$password],
                    'database' => $data[$database],
                    'prefix' => ''
                ));
        }
        catch(Exception $ex)
        {
            $this->invalidate($host, 'Failed to connect to database');
        }

        return true;
    }
    
    // Verify param not empty
    public function ldapValueNotEmpty($check, $Type)
    {
        $Value = current($check);
        $Key = key($check);
        
        if($Key == 'authLdapReferrals')
        {
            $IsBoolean = validation::boolean($Value);
            if($IsBoolean == false)
            {
                return 'is not on/off';
            }
        }
        
        else if(empty($Value))
        {
            return 'can not be empty';
        }
        
        return true;
    }
    

    // Verify database connectivity.  This actually does the validation for all database related fields.  It returns
    // true in most cases to prevent an unnecessary error message from appearing under the host field.
    public function verifyLdap($check, $serverUrl, $serverPort, $failoverUrl, $failoverUrl, $failoverPort, $protocol, 
        $referrals, $bindAccount, $bindPassword, $bindConfirmPassword, $root, $filter, $userField)
    {
        $type = current($check);
        
        $data = $this->data[$this->alias];
        $fields = compact('serverUrl', 'serverPort', 'failoverUrl', 'failoverUrl', 'failoverPort', 'protocol', 
                          'referrals', 'bindAccount', 'bindPassword', 'root', 'filter', 'userField');
        
        // Check to see if the connection parameters have changed.
        //if (!$this->changed($data, $fields))
        //  return true;

        // Verify that the password and confirmPassword fields are present and equal.
        if (!empty($data[$bindPassword]))
        {
            if (empty($data[$bindPassword]) || $data[$bindPassword] != $data[$bindConfirmPassword])
            {
                $this->invalidate($bindPassword, 'The password fields do not match.');
                $this->invalidate($bindConfirmPassword, 'The password fields do not match.');
                return true;
            }
        }

        // Abort validation at this point if any fields are empty.  The other validation rules will pick up the rest.
        // of the error messages.
        $optional = array($filter, $failoverUrl, $failoverPort, $bindAccount, $bindPassword, $referrals);
        foreach($fields as $field)
        {
            if (!in_array($field, $optional) && empty($data[$field]))
                return true;
        }

        // Test the LDAP connectivity information.
        $ldapc = Ldap::connect(array(
            'serverUrl' => $data[$serverUrl],
            'serverPort' => $data[$serverPort],
            'failoverUrl' => $data[$failoverUrl],
            'failoverPort' => $data[$failoverPort],
            'protocol' => $data[$protocol],
            'referrals' => $data[$referrals],
            'bind' => $data[$bindAccount],
            'bindPassword' => $data[$bindPassword],
        ));

        if (!$ldapc)
        {
            $this->invalidate($serverUrl, Ldap::Error());
            return true;
        }

        // Test the Failover connectivity information
        ldap_close($ldapc);
        if (!empty($data[$failoverUrl]))
        {
            if (empty($data[$failoverPort]))
                $data[$failoverPort] = $data[$serverPort];

            $ldapc = Ldap::connect(array(
                'serverUrl' => $data[$failoverUrl],
                'serverPort' => $data[$failoverPort],
                'failoverUrl' => $data[$failoverUrl],
                'failoverPort' => $data[$failoverPort],
                'protocol' => $data[$protocol],
                'referrals' => $data[$referrals],
                'bind' => $data[$bindAccount],
                'bindPassword' => $data[$bindPassword],
            ));

            if (!$ldapc)
            {
                $this->invalidate($failoverUrl, Ldap::Error());
                return true;
            }

            ldap_close($ldapc);
        }

        return true;
    }
};
