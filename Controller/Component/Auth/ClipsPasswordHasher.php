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
App::uses('SimplePasswordHasher', 'Controller/Component/Auth');

class ClipsPasswordHasher extends SimplePasswordHasher 
{
    private static $b64chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
    private static $cryptChars = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        
    public function __construct($config=array())
    {
        $default = array(
            'hashType' => 'blowfish',
            'salt' => true,
            // Changing this will invalidate any passwords stored.
            'cost' => 10
        );
        $config = Hash::merge($default, $config);
        parent::__construct($config);
    }
        
    public function hash($password)
    {
        $salt = mcrypt_create_iv(16, MCRYPT_DEV_URANDOM);
        return self::buildHash(1, $password, $salt, '2y', $this->_config['cost']);
    }

    public function migrate($oldhash)
    {
        if (strlen($oldhash) != 32)
            return false;

        $salt = mcrypt_create_iv(16, MCRYPT_DEV_URANDOM);
        return self::buildHash(0, $oldhash, $salt, '2y', $this->_config['cost']);
    }
    
    public function check($password, $hashedPassword) 
    {
        if (empty($hashedPassword))
            return false;
        
        // Get the version for new passwords.
        $version = $hashedPassword[0];
        if ($version == '0')
        {
            // Old passwords used a weak hashing algorithm (md5). To migrate old passwords, we hashed their old
            // md5 into the new algorithm. When comparing against an old password hash, md5 their password first
            // so that the hashes match.
            $password = md5($password);
        }
        else if ($version != '1')
            return false;
        
        // Decode our old hash
        list($prefix, $cost, $rndhash, $hashpart) = sscanf(
                substr($hashedPassword, 1), '$%2s$%2s$%22s%s');
        $rndsalt = base64_decode("$rndhash==");

        // Hash our new password with our old parameters
        $hash = self::buildHash($version, $password, $rndsalt, $prefix, $cost);

        // If the result is the same, the passwords match.
        return ($hash == $hashedPassword);
    }
    
    private static function buildHash($version, $password, $salt, $prefix, $cost)
    {
        $saltraw = self::combineSalt($salt);

        // Encode the salt while translating from base64 encoded to characters expected by crypt().
        $saltenc = substr(base64_encode($saltraw), 0, 22);
        $saltenc = strtr($saltenc, self::$b64chars, self::$cryptChars);

        // Generate the crypt hash
        $cryptsalt = sprintf('$%s$%02d$%s', $prefix, $cost, $saltenc);
        $crypthash = crypt($password, $cryptsalt);

        // Swap our the crypt() salt with our own salt. This obscures that we've combined it with our application salt.
        // We also prefix a version number to the resulting hash in case we ever need to make changes in the future.
        return sprintf('%s$%s$%02d$%s%s', 
                $version,                   // Version
                $prefix,                    // Blowfish prefix
                $cost,                      // Blowfish cost
                substr(base64_encode($salt), 0, 22), // Random salt (not the one actually used)
                substr($crypthash, 29)      // Crypt hash
            );
    }

    // Merges a salt value (must be 16 bytes) with the application salt defined by Security.Salt in the core.php
    // configuration file.
    private static function combineSalt($salt)
    {
        if (strlen($salt) != 16)
            return false;

        // Combine it withour application salt.
        $appsalt = Configure::read('Security.salt');
        $saltraw = hash('md5', $appsalt, true);
        for($a = 0; $a < 16; ++$a)
            $salt[$a] = $saltraw[$a] ^ $salt[$a];
        
        return $salt;
    }
}
