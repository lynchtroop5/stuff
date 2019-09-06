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

class PrefillValuesController extends AppController
{
    public $uses = array('Response','Prefill','PrefillResponseMap');

    function from_response($responseId)
    {
        if (!Licensing::enableResponsePrefill()) {
            return $this->redirect('/responses');
        }
        
        // Get the data mined tags
        $values = $this->Response->ResponseValue->find('all', array(
            'fields' => array('connectCicTag','connectCicTagValue'),
            'conditions' => array(
                'responseId' => $responseId
            )
        ));
        $values = Hash::combine($values, '{n}.ResponseValue.connectCicTag', '{n}.ResponseValue.connectCicTagValue');
        
        // Get the interface, system, and class.
        $interface = $this->CurrentAgency->interface;
        $result = $this->Response->read(array('agencyId','system','class'), $responseId);
        $system = $result['Response']['system'];
        $class = $result['Response']['class'];
        
        // Get the prefill map
        $prefillMap = $this->PrefillResponseMap->getPrefillMap($interface, $system, $class);
        
        // Find the highest rank tag's value. We'll use that to generate a banner. We also generate the values
        // array.
        $rank = 255;
        $rankValue = '';
        $prefillValues = array();
        foreach($prefillMap as $mapping)
        {
            $mapping = $mapping['PrefillResponseMap'];
            if (empty($values[$mapping['connectCicTag']]))
                continue;
            
            if (!empty($mapping['displayRank']))
            {
                $displayRank = (int)$mapping['displayRank'];
                if ($displayRank < $rank)
                {
                    $rank = $displayRank;
                    $rankValue = $values[$mapping['connectCicTag']];
                }
            }
            
            // Write the tags to the values array
            $index = (int)$mapping['index'];
            $base = $tag = $mapping['connectCicTag'];
            
            // We loop here to handle repeating fields (like AKA, SMT, OFF, etc.)
            do
            {
                if ($index > 0)
                    $tag = sprintf("%s%d", $base, $index + 1);
            
                if (empty($values[$tag]))
                    break;
                
                $prefillValues[] = array(
                    'category' => $mapping['category'],
                    'tag' => $mapping['tag'],
                    'index' => $index,
                    'value' => $values[$tag]
                );

                ++$index;
                $tag = sprintf("%s%d", $base, $index + 1);
            }
            while($mapping['repeat'] != 0);
        }

        // Generate a banner. 
        $banner = "$system $class";
        if (!empty($rankValue))
            $banner.= " $rankValue";
        
        // Save the prefill record and its values
        $this->Prefill->clear();
        $this->Prefill->saveAll(array(
            'agencyId' => $this->CurrentAgency->agencyId,
            'deviceAlias' => $this->CurrentDevice->deviceAlias,
            'banner' => $banner,
            'PrefillValue' => $prefillValues
        ));

        $systemClassMap= Configure::read('Interfaces.' . $this->CurrentAgency->interface . '.responsePrefillMap');
        $classMap = array();
        if (!empty($systemClassMap[$system]))
            $classMap = $systemClassMap[$system];
        else if (!empty($systemClassMap[0]))
            $classMap = $systemClassMap[0];

        
        $url = array(
            'controller' => 'forms', 
            'action' => 'index'
        );
        if (!empty($classMap[$class])) {
            $url['prefillId'] = $this->Prefill->id;
            $url[] = $this->CurrentAgency->interface . '_' . $classMap[$class];
        }

        // Kick the user back to the forms page.
        return $this->redirect($url);
    }
}
