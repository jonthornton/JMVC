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
	
	protected static $HOURS = Array('00:00'=>'12:00 am', '00:30'=>'12:30 am', '01:00'=>'1:00 am', '01:30'=>'1:30 am', '02:00'=>'2:00 am', '02:30'=>'2:30 am', 
		'03:00'=>'3:00 am', '03:30'=>'3:30 am', '04:00'=>'4:00 am',
		'04:30'=>'4:30 am', '05:00'=>'5:00 am', '05:30'=>'5:30 am', '06:00'=>'6:00 am', '06:30'=>'6:30 am', '07:00'=>'7:00 am',
		'07:30'=>'7:30 am', '08:00'=>'8:00 am', '08:30'=>'8:30 am', '09:00'=>'9:00 am', '09:30'=>'9:30 am', '10:00'=>'10:00 am',
		'10:30'=>'10:30 am', '11:00'=>'11:00 am', '11:30'=>'11:30 am', '12:00'=>'12:00 pm', '12:30'=>'12:30 pm',
		'13:00'=>'1:00 pm', '13:30'=>'1:30 pm', '14:00'=>'2:00 pm', '14:30'=>'2:30 pm', '15:00'=>'3:00 pm', '15:30'=>'3:30 pm',
		'16:00'=>'4:00 pm', '16:30'=>'4:30 pm', '17:00'=>'5:00 pm', '17:30'=>'5:30 pm', '18:00'=>'6:00 pm', '18:30'=>'6:30 pm',
		'19:00'=>'7:00 pm', '19:30'=>'7:30 pm', '20:00'=>'8:00 pm', '20:30'=>'8:30 pm', '21:00'=>'9:00 pm', '21:30'=>'9:30 pm',
		'22:00'=>'10:00 pm', '22:30'=>'10:30 pm', '23:00'=>'11:00 pm', '23:30'=>'11:30 pm');

	public function __construct($id=false)
	{
		$this->id = $id ?: 'form'.self::$formcount;
		
		if ($id) {
			if (isset($_POST[$this->id])) {
				parent::__construct($_POST);
			} else if (isset($_GET[$this->id])) {
				parent::__construct($_GET);
			} else {
				parent::__construct(array());
			}
		} else {
			if (!empty($_POST)) {
				parent::__construct($_POST);
			} else if (!empty($_GET)) {
				parent::__construct($_GET);
			} else {
				parent::__construct(array());
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
			return self::get_clean($this->data[$key]);
		}
	}
	
	public static function get_clean($data)
	{
		return htmlspecialchars(strip_tags(trim($data)), ENT_COMPAT, 'ISO-8859-1', false);
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
			if ($this->submitted() && !$this->errors[$data['name']] && isset($this->data[$data['name']]) && !is_array($this->data[$data['name']])) {
				$data['value'] = $this[$data['name']];
			} else if ($value !== null) {
				$data['value'] = $value;
			}
		}
		
		if ((isset($data['name']) && isset($this->errors[$data['name']])) || (isset($data['for']) && isset($this->messages[$data['for']]))) {
			self::add_class($data, 'error');
		}
		
		if (isset($data['for']) && $this->is_required($data['for'])) {
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
		return $this->input($data, null, $extra);
	}
	
	public function get_date($field)
	{
		if (!empty($this[$field])) {
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
			$data['value'] = self::int2time($data['value']);
		} else if (!isset($data['value'])) {
			$data['value'] = null;
		}
		
		return $this->dropdown($data, Array(''=>'Time...')+self::$HOURS, $data['value'], $extra);
	}
	
	public function int2time($ts)
	{
		if (!is_numeric($ts) || !$ts) {
            return $ts;
		}
		
		if ($ts < ONE_YEAR) {
			$ts += mktime(0, 0, 0);
		}
		
		$ts = 1800*round($ts/1800);
		
        return date('H:i', $ts);
	}
	
	public function get_date_time($date_key, $time_key)
	{
		if (!empty($this[$date_key]) && isset($this[$time_key])) {
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
		
		if (!isset($data['rows'])) $data['rows'] = 10;
		if (!isset($data['cols'])) $data['cols'] = 30;

		return '<textarea'.form::attributes($data, 'textarea').' '.$extra.'>'.htmlspecialchars(trim($value), ENT_COMPAT, 'ISO-8859-1', false).'</textarea>';
	}

	public function dropdown($data, $options, $selected=NULL, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}

		if ($this->submitted() && !$this->errors[$data['name']] && isset($this->data[$data['name']])) {
			$selected = $this[$data['name']];
		}
		
		if ((isset($data['name']) && isset($this->errors[$data['name']])) || (isset($data['for']) && isset($this->messages[$data['for']]))) {
			self::add_class($data, 'error');
		}
		
		if (isset($data['for']) && $this->is_required($data['for'])) {
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

	public function multiselect($data, $options, $selected=NULL, $extra = '')
	{
		if (!is_array($data)) {
			$data = array('name' => $data);
		}
		
		$data['multiple'] = 'multiple';
		

		if ($this->submitted() && !$this->errors[$data['name']] && isset($this->data[$data['name']])) {
			$selected = $this[$data['name']];
		}
		if (!is_array($selected)) {
			$selected = array($selected);
		}
		
		if ($this->errors[$data['name']] || $this->messages[$data['for']]) {
			self::add_class($data, 'error');
		}
		
		if ($this->is_required($data['for'])) {
			self::add_class($data, 'required');
		}
		
		$data['id'] = $data['name'];
		$data['name'] .= '[]';
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
				$sel = (in_array($key, $selected)) ? ' selected="selected"' : '';
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
		if (!isset($data['value']) || $data['value'] === null) $data['value'] = 1;

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
		
		if (isset($this->errors[$data['for']]) || isset($this->messages[$data['for']])) {
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
				case 'email':
				case 'search':
				case 'url':
				case 'number':
				case 'range':
				case 'date':
				case 'datetime':
					// Only specific types of inputs use name to id matching
					$attr['id'] = $attr['name'];
				break;
			}
		}
		
		if ($type == 'select') unset($attr['value']);

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