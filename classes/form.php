<?php 
/**
 * Form helper class.
 *
 * based on Kohana form class
 *
 * @license    http://kohanaphp.com/license.html
 */
 namespace jmvc\classes;
 
class Form extends Validation {

	protected static $formcount = 0;
	protected static $tabindex = 0;

	public function __construct($id=false)
	{
		$this->id = $id ?: 'form'.self::$formcount;
		
		if ($id) {
			if (isset($_POST[$this->id])) {
				parent::__construct($_POST);
			} else if (isset($_GET[$this->id])) {
				parent::__construct($_GET);
			} else {
				parent::__construct();
			}
		} else {
			if (!empty($_POST)) {
				parent::__construct($_POST);
			} else if (!empty($_GET)) {
				parent::__construct($_GET);
			} else {
				parent::__construct();
			}
		}
		
		self::$formcount++;
	}
	
	public function offsetGet($key)
	{
		if (is_array($this->data[$key])) {
			return $this->data[$key];
		} else if ($this->data[$key] == '') {
			return;
		} else {
			return htmlspecialchars(strip_tags(trim($this->data[$key])), ENT_COMPAT, 'ISO-8859-1', false);
		}
	}
	
	public function clear()
	{
		$this->data = array();
	}

	public function as_array()
	{
		$arr = $this->data;
		unset($arr[$this->id]);
		return $arr;
	}

	public function open($attr = array(), $action = CURRENT_URL, $hidden = NULL)
	{
		// Make sure that the method is always set
		if (empty($attr['method'])) $attr['method'] = 'post';

		if ($attr['method'] !== 'post' AND $attr['method'] !== 'get') {
			// If the method is invalid, use post
			$attr['method'] = 'post';
		}
		
		if (empty($attr['id'])) {
			$attr['id'] = $this->id;
		}

		// Set action
		if (empty($attr['action'])) $attr['action'] = $action;

		// Form opening tag
		$form = '<form'.form::attributes($attr).'>'."\n";

		// Add hidden fields immediate after opening tag
		empty($hidden) or $form .= $this->hidden($hidden);

		return $form;
	}
	
	public function open_multipart($attr = array(), $action = CURRENT_URL, $hidden = array())
	{
		// Set multi-part form type
		$attr['enctype'] = 'multipart/form-data';

		return $this->open($attr, $action, $hidden);
	}

	public function hidden($data, $value = '')
	{
		if ( ! is_array($data)) {
			$data = array($data => $value);
		}

		$input = '';
		foreach ($data as $name => $value) {
			$attr = array(
				'type'  => 'hidden',
				'name'  => $name,
				'value' => $value
			);

			$input .= $this->input($attr)."\n";
		}

		return $input;
	}

	public function input($data, $value=null, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}

		// Type and value are required attributes
		$data += array('type'=>'text');
		
		if ($data['type'] != 'checkbox' && $data['type'] != 'radio') {
			if ($this->submitted() && !$this->errors[$data['name']] && isset($this->data[$data['name']])) {
				$data['value'] = $this[$data['name']];
			} else if ($value !== null) {
				$data['value'] = $value;
			}
		}
		
		if ($this->errors[$data['name']] || $this->messages[$data['for']]) {
			self::add_class($data, 'error');
		}
		
		if ($this->is_required($data['for'])) {
			self::add_class($data, 'required');
		}

		return '<input'.form::attributes($data).' '.$extra.' />';
	}

	public function password($data, $value=false, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}

		$data['type'] = 'password';

		return $this->input($data, $value, $extra);
	}
	
	public function date($data, $value=false, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}
		
		if ($value) {
			$data['value'] = $value;
			unset($value);
		}
		
		if (isset($data['value']) && is_numeric($data['value'])) {
			$data['value'] = date('n/j/Y', $data['value']);
		}
		
		self::add_class($data, 'date');
		return $this->input($data, $value, $extra);
	}
	
	public function get_date($field)
	{
		if (isset($this[$field])) {
			return strtotime($this[$field]);
		}
	}
	
	public function time($data, $value=null, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}
		
		if ($value) {
			$data['value'] = $value;
			unset($value);
		}
		
		if (isset($data['value']) && is_numeric($data['value'])) {
			$data['value'] = \Util::getRoundedTime2($data['value']);
		}
		
		return $this->dropdown($data, Array(''=>'')+$GLOBALS['HOURS2'], $data['value'], $extra);
	}
	
	public function get_date_time($date_key, $time_key)
	{
		if ($this[$date_key] && $this[$time_key]) {
			return strtotime($this[$date_key].' '.$this[$time_key]);
		}
	}
	
	public function number($data, $value=false, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}
		self::add_class($data, 'num');
		$this->add_rules($data['name'], 'numeric');
		
		return $this->input($data, $value, $extra);
	}

	public function upload($data, $value=false, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}

		$data['type'] = 'file';

		return $this->input($data, $value, $extra);
	}
	
	public function get_upload($name)
	{
		if ($_FILES[$name]['error'] == 0) {
			return $_FILES[$name];
		} else {
			return;
		}
	}

	public function textarea($data, $value=false, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}
		
		if ($this->submitted() && !$this->errors[$data['name']]) {
			$data['value'] = $this[$data['name']];
		} else if ($value) {
			$data['value'] = $value;
		}

		// Use the value from $data if possible, or use $value
		$value = isset($data['value']) ? $data['value'] : '';

		// Value is not part of the attributes
		unset($data['value']);
		
		if ($this->errors[$data['name']] || $this->messages[$data['for']]) {
			self::add_class($data, 'error');
		}
		
		if ($this->is_required($data['for'])) {
			self::add_class($data, 'required');
		}

		return '<textarea'.form::attributes($data, 'textarea').' '.$extra.'>'.htmlspecialchars($value).'</textarea>';
	}

	public function dropdown($data, $options, $selected=NULL, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}

		if ($this->submitted() && !$this->errors[$data['name']] && isset($this->data[$data['name']])) {
			$selected = $this[$data['name']];
		}
		
		if ($this->errors[$data['name']] || $this->messages[$data['for']]) {
			self::add_class($data, 'error');
		}
		
		if ($this->is_required($data['for'])) {
			self::add_class($data, 'required');
		}
		
		$input = '<select'.form::attributes($data, 'select').' '.$extra.'>'."\n";
		foreach ((array) $options as $key => $val) {
			// Key should always be a string
			$key = (string) $key;

			if (is_array($val)) {
				$input .= '<optgroup label="'.$key.'">'."\n";
				foreach ($val as $inner_key => $inner_val) {
					// Inner key should always be a string
					$inner_key = (string) $inner_key;

					$sel = ($inner_key == $selected) ? ' selected="selected"' : '';
					$input .= '<option value="'.$inner_key.'"'.$sel.'>'.$inner_val.'</option>'."\n";
				}
				$input .= '</optgroup>'."\n";
			} else {
				$sel = ($key == $selected) ? ' selected="selected"' : '';
				$input .= '<option value="'.$key.'"'.$sel.'>'.$val.'</option>'."\n";
			}
		}
		$input .= '</select>';

		return $input;
	}

	public function checkbox($data, $checked = FALSE, $value = null, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}

		$data['type'] = 'checkbox';
		
		if ($value !== null) $data['value'] = $value;
		if ($data['value'] === null) $data['value'] = 1;

		if ($this->submitted()) {
			$name = rtrim($data['name'], '[]');
		
			if (is_array($this[$name]) && in_array($data['value'], $this[$name])) {
				$data['checked'] = 'checked';
			} else if (!is_array($this[$name]) && $this[$name]) {
				$data['checked'] = 'checked';
			} else {
				unset($data['checked']);
			}
		
		} else if ($checked || (isset($data['checked']) && $data['checked'] == TRUE)) {
			$data['checked'] = 'checked';
		} else {
			unset($data['checked']);
		}
			
		

		return $this->input($data, $value, $extra);
	}
	
	public function get_checkbox($key)
	{
		return $this[$key] ?: 0;
	}

	public function radio($data = '', $value = null, $checked = FALSE, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}
		if ($value !== null) {
			$data['value'] = $value;
		}

		$data['type'] = 'radio';
			
		if ($this->submitted() && $this[$data['name']] !== null) {
			if ($this[$data['name']] == $data['value']) {
				$data['checked'] = 'checked';
			} else {
				unset($data['checked']);
			}
		} else if ($checked || (isset($data['checked']) && $data['checked'] == TRUE)) {
			$data['checked'] = 'checked';
		} else {
			unset($data['checked']);
		}

		return $this->input($data, $value, $extra);
	}

	public function submit($text = '', $data = array(), $extra = '')
	{
		$data['type'] = 'submit';
		if (empty($text)) $text = 'Submit';
		
		return $this->hidden($this->id, 1).$this->button($text, $data, $extra);
	}
	
	public function button($text = '', $data=array(), $extra = '')
	{
		return '<button'.form::attributes($data, 'button').' '.$extra.'>'.$text.'</button>';
	}

	public function label($data, $text, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('for' => $data);
		}
		
		if ($this->errors[$data['for']] || $this->messages[$data['for']]) {
			self::add_class($data, 'error');
		}
		
		if ($this->is_required($data['for'])) {
			self::add_class($data, 'required');
		}

		return '<label'.form::attributes($data).' '.$extra.'>'.$text.'</label>';
	}
	
	public function formatted_errors()
	{
		$errors = $this->errors();
		
		if (empty($errors)) {
			return '';
		}
		
		$out = '<ul class="msg errors">';
		foreach ($errors as $error) {
			$out .= '<li>'.$error.'</li>';
		}
		
		return $out.'</ul>';
	}
	
	public static function add_class(&$data, $class)
	{
		if (isset($data['class'])) {
			$data['class'] .= ' '.$class;
		} else {
			$data['class'] = $class;
		}
	}

	public static function attributes($attr, $type = NULL)
	{
		if (empty($attr))
			return '';

		if (isset($attr['name']) AND empty($attr['id']) AND strpos($attr['name'], '[') === FALSE)
		{
			if ($type === NULL AND ! empty($attr['type']))
			{
				// Set the type by the attributes
				$type = $attr['type'];
			}

			switch ($type)
			{
				case 'text':
				case 'textarea':
				case 'password':
				case 'select':
				case 'checkbox':
				case 'file':
				case 'image':
				case 'button':
				case 'submit':
					// Only specific types of inputs use name to id matching
					$attr['id'] = $attr['name'];
				break;
			}
		}

		$order = array
		(
			'action',
			'method',
			'type',
			'id',
			'name',
			'value',
			'src',
			'size',
			'maxlength',
			'rows',
			'cols',
			'accept',
			'tabindex',
			'accesskey',
			'align',
			'alt',
			'title',
			'class',
			'style',
			'selected',
			'checked',
			'readonly',
			'disabled'
		);

		$sorted = array();
		foreach ($order as $key)
		{
			if (isset($attr[$key]))
			{
				// Move the attribute to the sorted array
				$sorted[$key] = $attr[$key];

				// Remove the attribute from unsorted array
				unset($attr[$key]);
			}
		}

		$attrs = array_merge($sorted, $attr);
		
		if (empty($attrs))
			return '';

		if (is_string($attrs))
			return ' '.$attrs;

		$compiled = '';
		foreach ($attrs as $key => $val) {
			$compiled .= ' '.$key.'="'.htmlspecialchars(trim($val), ENT_COMPAT, 'ISO-8859-1', false).'"';
		}

		return $compiled;
	}

} // End form