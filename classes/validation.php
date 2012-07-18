<?php
/**
 * Validation library.
 *
 * based on Kohnana validation object
 *
 * @license    http://kohanaphp.com/license.html
 */

 namespace jmvc\classes;

class Validation implements \ArrayAccess {

	// Filters
	protected $pre_filters = array();
	protected $post_filters = array();

	// Rules and callbacks
	protected $rules = array();
	protected $callbacks = array();

	// Rules that are allowed to run on empty fields
	protected $empty_rules = array('required', 'matches');

	// Errors
	protected $errors = array();
	protected $messages = array();
	public $error_messages = array();

	// Fields that are expected to be arrays
	protected $array_fields = array();

	// Checks if there is data to validate.
	protected $submitted;
	protected $data = array();

	public function __construct(array $data)
	{
		// The array is submitted if the array is not empty
		$this->submitted = !empty($data);

		$this->data = $data;
	}

	public function absorb($val)
	{
		$data = $val->export();
		$this->pre_filters = array_merge($this->pre_filters, $data['pre_filters']);
		$this->post_filters = array_merge($this->post_filters, $data['post_filters']);
		$this->rules = array_merge($this->rules, $data['rules']);
		$this->callbacks = array_merge($this->callbacks, $data['callbacks']);
		$this->empty_rules = array_merge($this->empty_rules, $data['empty_rules']);
		$this->error_messages = array_merge($this->error_messages, $data['error_messages']);
		$this->array_fields = array_merge($this->array_fields, $data['array_fields']);
	}

	public function export()
	{
		return array(
			'pre_filters'=>$this->pre_filters,
			'post_filters'=>$this->post_filters,
			'rules'=>$this->rules,
			'callbacks'=>$this->callbacks,
			'empty_rules'=>$this->empty_rules,
			'error_messages'=>$this->error_messages,
			'array_fields'=>$this->array_fields
		);
	}

	public function offsetGet($key)
	{
		return $this->data[$key];
	}

	public function offsetSet($key, $val)
	{
		$this->data[$key] = $val;
	}

	public function offsetExists($key)
	{
		return isset($this->data[$key]);
	}

	public function offsetUnset($key)
	{
		unset($this->data[$key]);
	}

	public function submitted()
	{
		return $this->submitted;
	}

	public function field_names()
	{
		// All the fields that are being validated
		$fields = array_keys(array_merge(
			$this->pre_filters,
			$this->rules,
			$this->callbacks,
			$this->post_filters
		));

		// Remove wildcard fields
		$fields = array_diff($fields, array('*'));

		return $fields;
	}

	public function as_array()
	{
		return $this->data;
	}

	protected function callback($callback)
	{
		if (is_string($callback)) {
			if (strpos($callback, '::') !== FALSE) {
				$callback = explode('::', $callback);
			} elseif (function_exists($callback)) {
				// No need to check if the callback is a method
				$callback = $callback;
			} elseif (method_exists($this, $callback)) {
				// The callback exists in Validation
				$callback = array($this, $callback);
			} elseif (method_exists('jmvc\classes\valid', $callback)) {
				// The callback exists in valid::
				$callback = array('jmvc\classes\valid', $callback);
			}
		}

		if (!is_callable($callback, FALSE)) {
			if (is_array($callback)) {
				if (is_object($callback[0])) {
					// Object instance syntax
					$name = get_class($callback[0]).'->'.$callback[1];
				} else {
					// Static class syntax
					$name = $callback[0].'::'.$callback[1];
				}
			} else {
				// Function syntax
				$name = $callback;
			}

			throw new \ErrorException('validation.not_callable '.$name);
		}

		return $callback;
	}

	public function pre_filter($filter, $field = TRUE)
	{
		if ($field === TRUE OR $field === '*') {
			// Use wildcard
			$fields = array('*');
		} else {
			// Add the filter to specific inputs
			$fields = func_get_args();
			$fields = array_slice($fields, 1);
		}

		// Convert to a proper callback
		$filter = $this->callback($filter);

		foreach ($fields as $field) {
			// Add the filter to specified field
			$this->pre_filters[$field][] = $filter;
		}

		return $this;
	}

	public function post_filter($filter, $field = TRUE)
	{
		if ($field === TRUE) {
			// Use wildcard
			$fields = array('*');
		} else {
			// Add the filter to specific inputs
			$fields = func_get_args();
			$fields = array_slice($fields, 1);
		}

		// Convert to a proper callback
		$filter = $this->callback($filter);

		foreach ($fields as $field) {
			// Add the filter to specified field
			$this->post_filters[$field][] = $filter;
		}

		return $this;
	}

	public function add_rules($field, $rules)
	{
		// Get the rules
		$rules = func_get_args();
		$rules = array_slice($rules, 1);

		if ($field === TRUE) {
			// Use wildcard
			$field = '*';
		}

		foreach ($rules as $rule) {
			// Arguments for rule
			$args = NULL;

			if (is_string($rule)) {
				if (preg_match('/^([^\[]++)\[(.+)\]$/', $rule, $matches)) {
					// Split the rule into the function and args
					$rule = $matches[1];
					$args = preg_split('/(?<!\\\\),\s*/', $matches[2]);

					// Replace escaped comma with comma
					$args = str_replace('\,', ',', $args);
				}
			}

			if ($rule === 'is_array') {
				// This field is expected to be an array
				$this->array_fields[$field] = $field;
			}

			// Convert to a proper callback
			$rule = $this->callback($rule);

			// Add the rule, with args, to the field
			$this->rules[$field][] = array($rule, $args);
		}

		return $this;
	}

	public function add_callbacks($field, $callbacks)
	{
		// Get all callbacks as an array
		$callbacks = func_get_args();
		$callbacks = array_slice($callbacks, 1);

		if ($field === TRUE) {
			// Use wildcard
			$field = '*';
		}

		foreach ($callbacks as $callback) {
			// Convert to a proper callback
			$callback = $this->callback($callback);

			// Add the callback to specified field
			$this->callbacks[$field][] = $callback;
		}

		return $this;
	}

	public function validate($array=false)
	{
		if (!$array) {
			if ($this->submitted()) {
				$array = $this->data;
			} else {
				return false;
			}
		}

		// Get all field names
		$fields = $this->field_names();

		foreach ($fields as $field) {
			if ($field === '*') {
				continue;
			}

			if ($array[$field] === '') {
				if (isset($this->array_fields[$field])) {
					// This field must be an array
					$array[$field] = array();
				} else {
					$array[$field] = NULL;
				}
			}
		}

		// Swap the array back into the object
		$this->data = $array;

		// Get all defined field names
		$fields = array_keys($array);

		foreach ($this->pre_filters as $field => $callbacks) {
			foreach ($callbacks as $callback) {
				if ($field === '*') {
					foreach ($fields as $f) {
						$this[$f] = is_array($this[$f]) ? array_map($callback, $this[$f]) : call_user_func($callback, $this[$f]);
					}
				} else {
					$this[$field] = is_array($this[$field]) ? array_map($callback, $this[$field]) : call_user_func($callback, $this[$field]);
				}
			}
		}

		if (!$this->submitted) {
			return FALSE;
		}

		foreach ($this->rules as $field => $callbacks) {

			foreach ($callbacks as $callback) {
				// Separate the callback and arguments
				list($callback, $args) = $callback;

				// Function or method name of the rule
				$rule = is_array($callback) ? $callback[1] : $callback;

				if (is_object($rule)) {
					// rule is a callback
					$rule = 'callback';
				}

				if ($field === '*') {
					foreach ($fields as $f) {
						// Note that continue, instead of break, is used when
						// applying rules using a wildcard, so that all fields
						// will be validated.

						if (isset($this->errors[$f])) {
							// Prevent other rules from being evaluated if an error has occurred
							continue;
						}

						if (empty($this[$f]) AND ! in_array($rule, $this->empty_rules)) {
							// This rule does not need to be processed on empty fields
							continue;
						}

						if ($args === NULL) {
							if (!call_user_func($callback, $this[$f])) {
								$this->errors[$f] = $rule;

								// Stop validating this field when an error is found
								continue;
							}
						} else {
							if (!call_user_func($callback, $this[$f], $args)) {
								$this->errors[$f] = $rule;

								// Stop validating this field when an error is found
								continue;
							}
						}
					}
				} else {
					if (isset($this->errors[$field])) {
						// Prevent other rules from being evaluated if an error has occurred
						break;
					}

					if (!in_array($rule, $this->empty_rules) AND ! $this->required($this[$field])) {
						// This rule does not need to be processed on empty fields
						continue;
					}

					if ($args === NULL) {
						if (!call_user_func($callback, $this[$field])) {
							$this->errors[$field] = $rule;

							// Stop validating this field when an error is found
							break;
						}
					} else {
						if (!call_user_func($callback, $this[$field], $args)) {
							$this->errors[$field] = $rule;

							// Stop validating this field when an error is found
							break;
						}
					}
				}
			}
		}

		if (!empty($this->errors)) return false;

		foreach ($this->callbacks as $field => $callbacks) {
			foreach ($callbacks as $callback) {
				if ($field === '*') {
					foreach ($fields as $f) {
						// Note that continue, instead of break, is used when
						// applying rules using a wildcard, so that all fields
						// will be validated.

						if (isset($this->errors[$f])) {
							// Stop validating this field when an error is found
							continue;
						}

						call_user_func($callback, $this, $f);
					}
				} else {
					if (isset($this->errors[$field])) {
						// Stop validating this field when an error is found
						break;
					}

					call_user_func($callback, $this, $field);
				}
			}
		}

		foreach ($this->post_filters as $field => $callbacks) {
			foreach ($callbacks as $callback) {
				if ($field === '*') {
					foreach ($fields as $f) {
						$this[$f] = is_array($this[$f]) ? array_map($callback, $this[$f]) : call_user_func($callback, $this[$f]);
					}
				} else {
					$this[$field] = is_array($this[$field]) ? array_map($callback, $this[$field]) : call_user_func($callback, $this[$field]);
				}
			}
		}

		// Return TRUE if there are no errors
		return $this->errors === array();
	}

	public function add_error($field, $name)
	{
		$this->errors[$field] = $name;
	}

	public function message($input, $message=false)
	{
		if ($message) {
			$this->messages[$input] = $message;
			return;
		} else {
			$messages = $this->errors();
			return $messages[$input];
		}
	}

	public function set_error_messages($messages)
	{
		$this->error_messages = array_merge($this->error_messages, $messages);
	}

	public function errors($messages=array())
	{
		$messages = array_merge($this->error_messages, $messages);

		$out = $this->messages;
		foreach ($this->errors as $input => $error) {
			if (isset($messages[$input])) {
				if (is_array($messages[$input])) {
					if (is_string($error) && (isset($messages[$input][$error]))) {
						$out[$input] = $messages[$input][$error];
					} else if (isset($messages[$input]['default'])) {
						$out[$input] = $messages[$input]['default'];
					} else {
						$out[$input] = $input.':'.$error;
					}
				} else {
					$out[$input] = $messages[$input];
				}

			} else {
				if (isset($messages['default'])) {
					$out[$input] = $messages['default'];
				} else {
					$out[$input] = $input.':'.$error;
				}
			}
		}

		return $out;
	}


	public function required($str)
	{
		if (is_array($str)) {
			return !empty($str);
		} else {
			return ! ($str === '' OR $str === NULL OR $str === FALSE);
		}
	}

	public function is_required($field)
	{
		if (isset($this->rules[$field])) {
			for ($i=0; $i<count($this->rules[$field]); $i++) {
				if (is_array($this->rules[$field][$i][0]) && $this->rules[$field][$i][0][1] == 'required') {
					return true;
				}
			}
		}

		return false;
	}

	public function matches($str, array $inputs)
	{
		foreach ($inputs as $key) {
			if ($str !== (isset($this[$key]) ? $this[$key] : NULL)) {
				return FALSE;
			}
		}

		return TRUE;
	}

	public function length($str, array $length)
	{
		if (!is_string($str)) {
			return FALSE;
		}

		$size = strlen($str);
		$status = FALSE;

		if (count($length) > 1) {
			list ($min, $max) = $length;

			if ($size >= $min AND $size <= $max) {
				$status = TRUE;
			}
		} else {
			$status = ($size === (int) $length[0]);
		}

		return $status;
	}

	public function depends_on($field, array $fields)
	{
		foreach ($fields as $depends_on) {
			if (!isset($this[$depends_on]) OR $this[$depends_on] == NULL) {
				return FALSE;
			}
		}

		return TRUE;
	}

	public function chars($value, array $chars)
	{
		return ! preg_match('![^'.implode('', $chars).']!u', $value);
	}

} // End Validation
