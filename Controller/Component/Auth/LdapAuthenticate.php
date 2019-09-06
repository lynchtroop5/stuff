<?php
/*******************************************************************************
 * Copyright 2014 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 ******************************************************************************/
App::uses('AuditLog', 'Lib');
App::uses('FindUserAuthenticate', 'Controller/Component/Auth');
App::uses('Ldap', 'Lib');

AuditLog::Define(array(
    'AUDIT_LDAP_BIND_FAILED' => array(
		'Authentication Error',
		'CLIPS was unable to connect or bind to the LDAP Server: %s.',
    ),
    'AUDIT_LDAP_SEARCH_FAILED' => array(
		'Authentication Error',
		'CLIPS was unable to search the LDAP Server: %s.',
    ),
    'AUTH_LDAP_AUTHENTICATION_FAILURE' => array(
        'Authentication Failure',
        'CLIPS was unable to authenticate the user against LDAP: %s.'
    ),
    'AUTH_LDAP_NO_PERMISSIONS' => array(
        'Authentication Failure',
        'The user could not log in because they do not belong to any permission groups.'
    ),
    'AUTH_LDAP_PASSWORD_EXPIRED' => array(
        'Authentication Failure',
        'The user\'s password is expired.'
    )
));

class LdapAuthenticate extends FindUserAuthenticate
{
    public $clips_policies = array(
        'enforce_password_policies' => false,
        'change_password'           => false,
        'manage_users'              => true
    );
    
    private $captureFields = array(
        'agencyName'    => null,
        'userName'      => 'samaccountname',
        'userFirstName' => 'givenname',
        'userLastName'  => 'sn',
        'interfaceId'   => null,
        'memberships'   => null
    );
    
    public function __construct(ComponentCollection $collection, $settings=array())
    {
        if (!function_exists('ldap_connect'))
            throw new ClipsException('PHP LDAP Extension is not enabled');

        $default = array(
            'serverPort' => 389,
            'failoverPort' => 389,
            'protocol' => 3,
            'referrals' => 0,
            'manageUsers' => true,
            'userField' => 'samaccountname',
            'captureFields' => $this->captureFields
        );
        $settings = Hash::merge($default, $settings);

        $this->clips_policies['manage_users'] = $settings['manageUsers'];
        unset($settings['manageUsers']);

        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'LdapAuthenticate settings: ';
            print_r($settings);
            echo "\r\n";
            cs_debug_end_capture();
        }
        
        parent::__construct($collection, $settings);
    }
    
    public function authenticate(CakeRequest $request, CakeResponse $response)
    {
        $userData = $request->data['User'];

        $conf = $this->settings;
        $conf['serverPort'] = (int)$conf['serverPort'];
        $conf['failoverPort'] = (int)$conf['failoverPort'];
        $conf['protocol'] = (int)$conf['protocol'];
        $conf['referrals'] = (int)$conf['referrals'];

        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'LdapAuthenticate::authenticate() conf: ';
            print_r($conf);
            echo "\r\n";
            cs_debug_end_capture();
        }
        
        $ldapc = Ldap::Connect($conf);
        if (empty($ldapc))
        {
            AuditLog::Log(AUDIT_LDAP_BIND_FAILED, Ldap::Error());
            return false;
        }

        $maxPwdAge = '0';
        $ldapMaxAge = @ldap_read($ldapc, 'DC=ad,DC=commsys,DC=com', 'objectClass=*', array('maxpwdage'));
        if ($ldapMaxAge)
        {
            $resultMaxPwdAge = @ldap_get_entries($ldapc, $ldapMaxAge);
            @ldap_free_result($ldapMaxAge);
            if (!empty($resultMaxPwdAge[0]['maxpwdage'][0]))
                $maxPwdAge = $resultMaxPwdAge[0]['maxpwdage'][0];
        }
        
        $userName = Ldap::escape($userData['userName']);
        $filter = "(&{$conf['filter']}({$conf['userField']}=$userName))";

        $captureFields = array($conf['userField'], 'dn', 'pwdlastset', 'useraccountcontrol');
        $captureFields = array_merge($captureFields, array_values($conf['captureFields']));

        $ldaps = @ldap_search($ldapc, $conf['root'], $filter, $captureFields);
        if (empty($ldaps))
        {
            AuditLog::Log(AUDIT_LDAP_SEARCH_FAILED, Ldap::Error());
            ldap_close($ldapc);
            return false;
        }

        $results = @ldap_get_entries($ldapc, $ldaps);
        if (empty($results))
        {
            AuditLog::Log(AUDIT_LDAP_SEARCH_FAILED, Ldap::Error());
            CakeLog::write('error', 'Failed to retreive LDAP search results: ' . Ldap::Error($ldapc));
            ldap_close($ldapc);
            return false;
        }
        
        for($a = 0; $a < $results['count']; ++$a)
        {
            $record = $results[$a];
            if (@ldap_bind($ldapc, $record['dn'], $userData['userPassword']))
                break;

            unset($record);
        }

        ldap_free_result($ldaps);
        ldap_close($ldapc);
        
        if (empty($record))
        {
            AuditLog::Log(AUTH_LDAP_AUTHENTICATION_FAILURE, Ldap::Error());
            CakeLog::write('error', 'No LDAP account could be bound.');
            return false;
        }

        $pwdLastSetDate = $record['pwdlastset'][0];
        unset($record['pwdlastset']);
        
        $userAccountControl = (int)$record['useraccountcontrol'][0];
        unset($record['useraccountcontrol']);

        // Check to see if the password can expire
        if ($maxPwdAge != '0' && ($userAccountControl & 65536) != 0)
        {
            // Add password last set date and max age.  We use bcsub because the numbers are too large
            // for PHP to handle and because maxPwdAge is always going to be negative due to the way 
            // microsoft stores it.
            $expires = bcsub($pwdLastSetDate, $maxPwdAge); // YES, bcsub is correct, not bcadd.
            // Convert to a unix time stamp by converting $expires to seconds instead of nano-seconds,
            // and subtracting the difference between the two different start times. (i.e. expires is
            // from Jan 1, 1601 and unix is Jan 1, 1970.  The difference between them, in seconds, is
            // 11644473600.
            $expires = bcsub(bcdiv($expires, '10000000'), '11644473600');
            if ($expires <= time())
            {
                ldap_free_result($ldaps);
                ldap_close($ldapc);
                CakeLog::write('error', 'User account password is expired.');
                AuditLog::Log(AUTH_LDAP_PASSWORD_EXPIRED);
                $this->error = 'User password expired.';
                return false;
            }
        }
        
        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'LdapAuthenticate::authenticate() record: ';
            print_r($record);
            echo "\r\n";
            cs_debug_end_capture();
        }
        
        $ldapData = array();
        foreach($conf['captureFields'] as $internal => $external)
        {
            if (empty($external) || empty($record[$external]['count']))
                continue;
            
            if ($record[$external]['count'] == 1)
                $ldapData[$internal] = $record[$external][0];
            else
            {
                $ldapData[$internal] = $record[$external];
                unset($ldapData[$internal]['count']);
            }
        }

        if (!empty($ldapData['memberships']))
        {
            if (!is_array($ldapData['memberships']))
                $ldapData['memberships'] = array($ldapData['memberships']);
            
            $normalized = $this->parseMemberships($ldapData['memberships']);
            unset($ldapData['memberships']);

            if (empty($request->data['Agency']['agencyId']))
            {
                // User is in SysAdmin group.
                if (!empty($conf['sysAdminGroup']) && in_array($conf['sysAdminGroup'], $normalized))
                    $ldapData['isPowerUser'] = 1;
            }
            else
                $ldapData['memberships'] = $this->intersectGroups($normalized);
        }
        
        $ret = $ldapData;

        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'LdapAuthenticate::authenticate() ret: ';
            print_r($ldapData);
            echo "\r\n";
            cs_debug_end_capture();
        }

        $dbUser = parent::authenticate($request, $response);
        if (!empty($dbUser))
        {
            if (Configure::read('debug') > 1)
            {
                cs_debug_begin_capture();
                echo 'LdapAuthenticate::authenticate() dbUser: ';
                print_r($dbUser);
                echo "\r\n";
                cs_debug_end_capture();
            }

            $ret = array_merge($dbUser, $ret);
            
            if (Configure::read('debug') > 1)
            {
                cs_debug_begin_capture();
                echo 'LdapAuthenticate::authenticate() ret (merged): ';
                print_r($ret);
                echo "\r\n";
                cs_debug_end_capture();
            }
        }
        else
            cs_debug_write("LdapAuthenticate::authenticate() dbUser empty\r\n");
        
        //if (empty($dbRecord['membership']))
        //{
        //    AuditLog::Log(AUTH_LDAP_NO_PERMISSIONS);
        //    CakeLog::write('error', 
        //        'User could not be authenticated because they do not belong to any permission groups.');
        //    return false;
        //}

        return $ret;
    }

    private function parseMemberships($memberships)
    {
        $escaped = array(
            '\\,' => ',',
            '\\\\' => '\\',
            '\\#' => '#',
            '\\+' => '+',
            '\\<' => '<',
            '\\>' => '>',
            '\\;' => ';',
            '\\"' => '"',
            '\\=' => '='
        );

        $groups = array();
        foreach($memberships as $membership)
        {
            $matches = array();
            if (!preg_match('/^CN=(.*?)(?<!\\\\),/i', $membership, $matches))
                continue;
            $groups[] = strtr($matches[1], $escaped);
        }

        return $groups;
    }
    
    private function intersectGroups($memberships)
    {
        // Intersect the AD groups with those defined in CLIPS.
        $m = ClassRegistry::init('Group');
        $groups = $m->find('list', array(
            'conditions' => array(
                'agencyId' => CurrentAgency::agency('agencyId'),
                'groupName' => $memberships
            )
        ));

        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'LdapAuthenticate::intersectGroups() groups: ';
            print_r($groups);
            echo "\r\n";
            cs_debug_end_capture();
        }
        
        return $groups;
    }
}
