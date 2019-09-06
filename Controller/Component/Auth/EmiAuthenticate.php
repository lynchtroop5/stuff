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
App::uses('AuditLog', 'Lib');
App::uses('HttpSocket', 'Network/Http');
App::uses('ClipsAuthenticate', 'Controller/Component/Auth');

/*// Good Response:
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http:www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http:www.w3.org/2001/XMLSchema" xmlns:soap="http:schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetEmployeeInformationResponse xmlns="http:intergraph.com/">
      <GetEmployeeInformationResult>
        <recordID>100001132</recordID>
        <id>08424</id>
        <badgeNumber>051096</badgeNumber>
        <firstName>FIRST</firstName>
        <lastname>NAME</lastname>
        <agency>PHX</agency>
        <toc>decimal</toc>
        <tocLevel>string</tocLevel>
        <tocIssueDate>dateTime</tocIssueDate>
        <tocExpiration>dateTime</tocExpiration>
        <status>true</status>
        <message>Employee Found</message>
      </GetEmployeeInformationResult>
    </GetEmployeeInformationResponse>
  </soap:Body>
</soap:Envelope>
*/

/*// Good Negative Response:
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http:www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http:www.w3.org/2001/XMLSchema" xmlns:soap="http:schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetEmployeeInformationResponse xmlns="http:intergraph.com/">
      <GetEmployeeInformationResult>
        <status>false</status>
        <message>Employee not found</message>
      </GetEmployeeInformationResult>
    </GetEmployeeInformationResponse>
  </soap:Body>
</soap:Envelope>
*/

AuditLog::Define(array(
    'AUDIT_MEI_EXCEPTION' => array(
        'Authentication Failure',
        'An unexpected error occurred while authenticating the user against the MEI: %s'
    ),
    'AUDIT_MEI_NO_USER' => array(
        'Authentication Failure',
        'The MEI database failed to return the user account: %s'
    ),
    'AUDIT_MEI_TOC_EXPIRED' => array(
        'Authentication Failure',
        'The TOC is expired according to the MEI database'
    )
));

class EmiAuthenticate extends BaseAuthenticate
{ 
    private $captureFields = array(
        'stateUserId' => 'toc'
    );

    public $clips_policies = array(
        'enforce_password_policies' => false,
        'change_password'           => false,
        'two_factor'                => false,
        'manage_users'              => false
    );
    
    // Namespace array used to normalize XML Namespace prefixes to what we use internally.
    private static $namespaces = array(
        'soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
        'tn' => 'http://intergraph.com/'
    );

    public function __construct(ComponentCollection $collection, $settings=array())
    {
        // Setup default settings.
        $default = array(
            'servicePath' => ''
        );
        $settings = Hash::merge($default, $settings);

        if (Configure::read('debug') > 1)
        {
            cs_debug_begin_capture();
            echo 'EmiAuthenticate settings: ';
            print_r($settings);
            echo "\r\n";
            cs_debug_end_capture();
        }
        
        parent::__construct($collection, $settings);
    }
    
	public function authenticate(CakeRequest $request, CakeResponse $response)
    {
        $template =
            '<?xml version="1.0" encoding="utf-8"?>' .
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' .
                '<soap:Body>' .
                    '<GetEmployeeInformation xmlns="http://intergraph.com/">' .
                        '<userID>%s</userID>' .
                        '<Agency>%s</Agency >' .
                    '</GetEmployeeInformation>' .
                '</soap:Body>' .
            '</soap:Envelope>';

        try 
        {
            $xml = sprintf($template,
                // Intergraph requires the user name in upper case
                strtoupper($request->data['User']['userName']),
                // Agency is hard coded to PHX per Intergraph
                'PHX');//CurrentAgency::agency('agencyName'));

            if (Configure::read('debug') > 1)
                cs_debug_write("EmiAuthenticate::authenticate() POST data: $xml\r\n");

            $http = new HttpSocket();
            $response = $http->post(
                    $this->settings['servicePath'],
                    $xml, 
                    array('header' => array(
                            'SoapAction' => 'http://intergraph.com/GetEmployeeInformation',
                            'Content-Type' => 'text/xml'
                        ))
                );

            $body = $response->body();
            if (empty($body))
                throw new Exception('MEI response body was empty: HTTP Code ' . $response->statusCode());

            if (Configure::read('debug') > 1)
                cs_debug_write("EmiAuthenticate::authenticate() POST response: $body\r\n");

            $result = Xml::build($body);
            if ($result === false)
                throw new Exception('MEI response body could not be parsed as XML.'/* . $body*/);

            $result = Set::reverse($result);
            if (empty($result['Envelope']))
                throw new Exception('MEI response did not contain a SOAP Envelope'/* . $body*/);

            $result = $result['Envelope']; 
            if (!empty($result['soap:fault']))
            {
                ob_start();
                print_r($result['soap:fault']);
                $fault = ob_get_contents();
                ob_end_clean();
                throw new Exception('MEI response SOAP Fault: ' . $fault);
            }

            if (empty($result['soap:Body']))
                throw new Exception('MEI response did not contain SOAP Body.'/* . $body*/);

            $result = $result['soap:Body'];
            if (empty($result['GetEmployeeInformationResponse']['GetEmployeeInformationResult']))
                throw new Exception('MEI response did not contain Employee Information data.'/* . $body*/);

            $result = $result['GetEmployeeInformationResponse']['GetEmployeeInformationResult'];
            if (empty($result['status']) || strtolower($result['status']) !== 'true')
            {
                $reason = ((empty($result['message'])) ? 'Unknown reason' : $result['message']);
                
                AuditLog::Log(AUDIT_MEI_NO_USER, $reason);
                CakeLog::error('MEI did not find the user ' . $request->data['User']['userName'] . 
                               ': ' . $reason) ;
                return false;
            }

            // If no expiration is specified, assume it's expired.
            if (empty($result['tocExpiration']))
                $dt = false;
            else
                $dt = strtotime($result['tocExpiration']);

            // Check for expiration.
            if ($dt === false || $dt <= time())
            {
                AuditLog::Log(AUDIT_MEI_TOC_EXPIRED);
                CakeLog::error('TOC for user ' . $request->data['User']['userName'] . 
                               ' expired on ' . $result['tocExpiration']);
                $this->error = 'Your TOC is expired.';
                return false;
            }

            // TODO: Grab TOC level (group) from data to use for CLIPS permissions
            // HACK: Presently this is being determined by getting it from the 
            //       AD groups (via LdapAuthenticate).

            $data = array();
            foreach($this->captureFields as $internal => $external)
                $data[$internal] = $result[$external];

            if (Configure::read('debug') > 1)
            {
                cs_debug_begin_capture();
                echo 'EmiAuthenticate::authenticate() data: ';
                print_r($data);
                echo "\r\n";
                cs_debug_end_capture();
            }
            
            return $data;
        }
        catch(Exception $ex)
        {
            AuditLog::Log(AUDIT_MEI_EXCEPTION, $ex->getMessage());
            CakeLog::write('error', 'Unexpected exception during authentication: ' . $ex->getMessage() . "\r\nContent:\r\n" . $body);
            return false;
        }
    }
}
