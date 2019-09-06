<?php
class ConnectCic extends AppModel
{
    public $useTable = false;
    public $validateRules = array(
        'article' => array(
        ),
        'gun' => array(
        ),
        'person-name' => array(
            'BirthDate' => array(
                'format' => array(
                    'rule' => 'numeric',
                    'message' => 'Birthdate must be of the format CCYYMMDD'
                ),
                'minLength' => array(
                    'rule' => array('minLength', 8),
                    'message' => 'Birthdate must be of the format CCYYMMDD'
                ),
                'maxLength' => array(
                    'rule' => array('maxLength', 8),
                    'message' => 'Birthdate must be of the format CCYYMMDD'
                ),
                'date' => array(
                    'rule' => 'ncicDate',
                    'message' => 'Birthdate must be of the format CCYYMMDD'
                )
            )
        ),
        'person-oln' => array(
            
        ),
        'vehicle' => array(
            
        )
    );
    
    public function setForm($form)
    {
        $this->validate = $this->validateRules[$form];
    }

    public function ncicDate($data)
    {
        $value = current($data);
        $year = substr($value, 0, 4);
        $month = substr($value, 4, 2);
        $day = substr($value, 6, 2);

        return ((int)$year >= 1900) && checkdate($month, $day, $year);
    }
}
