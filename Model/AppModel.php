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
App::uses('Model', 'Model');
App::uses('Profiler', 'Lib');
App::uses('ValidationException', 'Error/Exception');

// An application specific Base Model used to provide common functionality to all models.
class AppModel extends Model
{
	// Tell CakePHP to stop grabbing all related information automatically.
	public $recursive = -1;
	public $actsAs = array('Containable');

    // Override "create" to deal with a CakePHP bug where it mistakes SQL Server's "getdate()" as "getdate(", causing
    // SQL errors.  If we find these values, we remove them from the data array.  They should be populated by a database
    // default constraint.  If not, you'll need to manually specify the value as part of $data or when calling save().
    public function create($data=array(), $filterKey=false)
    {
        parent::create($data, $filterKey);

        if (!empty($this->data[$this->alias]))
        {
            $data = $this->data[$this->alias];
            foreach($data as $key => $value)
            {
                if ($value == 'getdate(')
                    unset($this->data[$this->alias][$key]);
            }
        }

        return $this->data;
    }

    // Optionally starts, rolls back, or committs a transaction and returns success or failure of the overall operation.
    //
    // If transactions are used, $atomic is true, the return value indicates the success or failure of the begin() or
    // commit() call.  If $complete is false, false will always be returned after the call to rollback().
    //
    // If transactions are not used, the function always returns true if $complete is null or returns $complete for any
    // other value.
    //
    // $atomic   $complete  Return
    // ------------------------------------------------------------------------------------------------------
    // true      null       Returns the value of begin().
    // true      true       Returns the value of commit().
    // true      false      Calls rollback() and always returns false.
    // false     null       Immediately returns true.  No other action is taken.
    // false     *          Immediately returns $complete.  No other action is taken.
    //
    // Example usage:
    //
    // $options = array_merge(array('atomic' => true), $options);
    // if (!$this->transaction(null, $options['atomic']))
    //    return false;
    //
    // $success = $this->save($data);
    // return $this->transaction($success, $options['atomic']);
    public function transaction($complete, $atomic)
    {
        if (!$atomic)
            return (($complete === null) ? true : $complete);

        if ($complete === null)
            return $this->getDataSource()->begin();

        if ($complete === true)
            return $this->getDataSource()->commit();

        $this->getDataSource()->rollback();
        return false;
    }

	// Validate that one or more fields within the set of fields identified by $check and $target contain one or
	// more values.  $bounds can be used to control the minimum and maximum number of values.  By default, the
	// value of $bounds is array('min' => 1, 'max' => null), meaning at least one or more fields have a value.
	public function notEmptyList($check, $targets, $bounds=null)
	{
		// An undocumented "feature" of CakePHP is to pass the validation parameters as the last parameter to
		// the function call.  This makes having optional validation parameters annoying.
		if (is_array($bounds))
		{
			if (isset($bounds['rule']))
				$bounds = null;
		}

		$checks = 0;
		if ($bounds === null)
			// Store a reference to $checks as 'max'.  ($checks > $bounds['max']) will never evaluate to true,
			// and we don't have to test for some special value.
			$bounds = array('min' => 1, 'max' => &$checks);

		if (!is_array($targets))
			$targets = array($targets);

		$targets[] = key($check);
		foreach($targets as $target)
		{
			if (Validation::notEmpty($this->data[$this->name][$target]))
			{
				++$checks;
				if ($checks > $bounds['max'])
					return false;
			}
		}

		return ($checks >= $bounds['min']);
	}

	public function notRowExists($check, $field=null, $conditions=array())
	{
		return !$this->exists($check, $field, $conditions);
	}

	// Compares a field's value with additional fields.  Returns true if the fields are not equal.
	public function fieldsEqual($check, $targets)
	{
		if (!is_array($targets))
			$targets = array($targets);

		$ret = true;
		$value = strtolower(current($check));
		foreach($targets as $target)
		{
			if (!Validation::equalTo($value, strtolower($this->data[$this->name][$target])))
				return false;
		}

		return true;
	}

	// Compares a field's value with additional fields.  Returns true if the fields are not equal.
	public function notFieldsEqual($check, $targets)
	{
		return !$this->fieldsEqual($check, $targets);
	}

	// Verify that the specified value exists.  Uses CakePHP's Model.Field syntax in the $field parameter.
	// If Model is not specified as part of $field, it is assumed $this.  Specify additional requirements in
	// $conditions.
	public function rowExists($check, $field=null, $conditions=array())
	{
		// An undocumented "feature" of CakePHP is to pass the validation parameters as the last parameter to
		// the function call.  This makes having optional validation parameters annoying.
		if (is_array($field) && isset($field['rule']))
			$field = null;

		if (isset($conditions['rule']))
			$conditions = array();

		$model = $this;
		if (!$field)
			$field = $this->name . '.' . key($check);
		else if (($sep = strpos($field, '.')) !== false)
		{
			// Get the model
			$modelName = substr($field, 0, $sep);
			if (isset($this->$modelName))
				$model = $this->$modelName;
			else
			{
				// The model doesn't exist as a child of this model, so load it manually.
				$model = ClassRegistry::init($modelName);
			}
		}

		$conditions[$field] = current($check);
		return $model->hasAny($conditions);
	}

	// Custom validation function which verifies the uniqueness of a record based on one or more columns.
	public function unique($values, $fields=array())
	{
		// An undocumented "feature" of CakePHP is to pass the validation parameters as the last parameter to
		// the function call.  This makes having optional validation parameters annoying.
		if (is_array($fields) && isset($fields['rule']))
			$fields = array();

		if (!is_array($fields))
			$fields = array($fields);

		// $values already has the current field, and its value.  Add any additional fields specified by $fields.
		foreach($fields as $field)
			$values[$field] = $this->data[$this->name][$field];

		return $this->isUnique($values, false);
	}
}
