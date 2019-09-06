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

class ConnectCicResponsesController extends AppController
{
    public function beforeFilter()
    {
        $this->Auth->allow(array('submit'));
        parent::beforeFilter();
    }

    public function submit()
    {
        $dom = $this->request->input('Xml::build',array('return' => 'domdocument'));
        $xPath = new DOMXpath($dom);
        $xPath->registerNameSpace('s','http://schemas.xmlsoap.org/soap/envelope/');
        $xPath->registerNameSpace('wsi','http://schema.commsys.com/xml/ConnectCic/Api/Soap');
        $result = $xPath->query('//wsi:Message');
        $messageNode = $result->item(0);
        $dom = Xml::build($messageNode->textContent, array('return' => 'domdocument'));
        $xPath = new DOMXPath($dom);
        $xPath->registerNameSpace('api','http://schema.commsys.com/xml/ConnectCic/Api');
        $result = $xPath->query('//api:Transaction');
        $transactionNode = $result->item(0);
        $transactionId = $transactionNode->getAttribute('id');
        // Need destination alias
        $result = $xPath->query('//api:Destination');
        $result = $xPath->query('//LawEnforcementTransaction');
        $lawEnforcementMessage = $result->item(0);
    
        $soapXml =<<<XML
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<s:Body>
		<wsi:SendMessageResult xmlns:wsi="http://schema.commsys.com/xml/ConnectCic/Api/Soap">
			<wsi:ResponseURL/>
			<wsi:Message>
            </wsi:Message>
		</wsi:SendMessageResult>
	</s:Body>
</s:Envelope>
XML;
        $connectCicXml = <<<XML
<api:ConnectCicApi xmlns:api="http://schema.commsys.com/xml/ConnectCic/Api"></api:ConnectCicApi>
    <api:Route>
        <api:Source>{$this->CurrentDevice->deviceAlias}</api:Source>
        <api:Destinations>
            <api:Destination>CONNECTCIC</api:Destination>
        </api:Destinations>
    </api:Route>
    <api:Transactions>
        <api:TransactionResult id="{$transactionId}">
            <api:Status>Succeeded</api:Status>
        </api:TransactionResult>
    </api:Transactions>
</api:ConnectCicApi>
XML;
        $dom = Xml::build($connectCicXml, array('return' => 'domdocument'));
        $dom = Xml::build($soapXml, array('return' => 'domdocument'));
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $xPath = new DOMXpath($dom);
        $xPath->registerNameSpace('s','http://schemas.xmlsoap.org/soap/envelope/');
        $xPath->registerNameSpace('wsi','http://schema.commsys.com/xml/ConnectCic/Api/Soap');
        $result = $xPath->query('//wsi:Message');
        $responseNode = $result->item(0);
        $responseNode->textContent = $connectCicXml;
        $response = $dom->saveXml();
        echo $response;
        die;
    }
}