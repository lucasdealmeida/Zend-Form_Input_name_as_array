<?php
class lib_Form extends Zend_Form{
	
	public function getValues($suppressArrayNotation = false)
    {
        $values = array();
        $eBelongTo = null;

        if ($this->isArray()) {
            $eBelongTo = $this->getElementsBelongTo();
        }

        foreach ($this->getElements() as $key => $element) {
            if (!$element->getIgnore()){
                $merge = $this->_attachToArray($element->getValue(), $key);
                $values = $this->_array_replace_recursive($values, $merge);
            }
        }
        foreach ($this->getSubForms() as $key => $subForm) {
            $merge = array();
            if (!$subForm->isArray()) {
                $merge[$key] = $subForm->getValues();
            } else {
                $merge = $this->_attachToArray($subForm->getValues(true),
                                               $subForm->getElementsBelongTo());
            }
            $values = $this->_array_replace_recursive($values, $merge);
        }

        if (!$suppressArrayNotation &&
            $this->isArray() &&
            !$this->_getIsRendered()) {
            $values = $this->_attachToArray($values, $this->getElementsBelongTo());
        }

        return $values;
    }
	
	public function addElement($element, $options = null)
    {
        if ($element instanceof Zend_Form_Element) {
            $prefixPaths              = array();
            $prefixPaths['decorator'] = $this->getPluginLoader('decorator')->getPaths();
            if (!empty($this->_elementPrefixPaths)) {
                $prefixPaths = array_merge($prefixPaths, $this->_elementPrefixPaths);
            }
            if ($element->getBelongsTo()){
            	$name = $element->getBelongsTo()."[".$element->getName()."]";	
            }else{
            	$name = $element->getName();
            }

            $this->_elements[$name] = $element;
            $this->_elements[$name]->addPrefixPaths($prefixPaths);
        } else {
            require_once 'Zend/Form/Exception.php';
            throw new Zend_Form_Exception('Element must be specified by string or Zend_Form_Element instance');
        }

        $this->_order[$name] = $this->_elements[$name]->getOrder();
        $this->_orderUpdated = true;
        $this->_setElementsBelongTo($name);

        return $this;
    }
    
 	public function isValid($data)
 	{
    	$this->preValid($data);
    	
        if (!is_array($data)) {
            require_once 'Zend/Form/Exception.php';
            throw new Zend_Form_Exception(__METHOD__ . ' expects an array');
        }
        $translator = $this->getTranslator();
        $valid      = true;
        $eBelongTo  = null;

        if ($this->isArray()) {
            $eBelongTo = $this->getElementsBelongTo();
            $data = $this->_dissolveArrayValue($data, $eBelongTo);
        }
        $context = $data;
        foreach ($this->getElements() as $key => $element) {
            if (null !== $translator && $this->hasTranslator()
                    && !$element->hasTranslator()) {
                $element->setTranslator($translator);
            }
            $check = $data;
            if (($belongsTo = $element->getBelongsTo()) !== $eBelongTo) {
               $check = $this->_dissolveArrayValue($data, $belongsTo);
            }
            if (!isset($check[$key]) and !isset($check[$element->getName()])){
                $valid = $element->isValid(null, $context) && $valid;
            } else {
                $valid = $element->isValid($check[$element->getName()], $context) && $valid;
                $data = $this->_dissolveArrayUnsetKey($data, $belongsTo, $key);
            }
        }
        foreach ($this->getSubForms() as $key => $form) {
            if (null !== $translator && !$form->hasTranslator()) {
                $form->setTranslator($translator);
            }
            if (isset($data[$key]) && !$form->isArray()) {
                $valid = $form->isValid($data[$key]) && $valid;
            } else {
                $valid = $form->isValid($data) && $valid;
            }
        }

        $this->_errorsExist = !$valid;

        // If manually flagged as an error, return invalid status
        if ($this->_errorsForced) {
            return false;
        }

        return $valid;
 	}
 	
	public function preValid($data){
		foreach ($data as $name => $value){
			if (is_array($value)){
				$this->arrayToString($value, $name);
			}
		}
	}
	
	private function arrayToString($array, $name){
		foreach ($array as $key => $value){
			if (is_array($value)){
				$this->arrayToString($value, $name. "[{$key}]");
			}else{
				$this->addNewField($key, $name, $value, $this->getOption($this->getNameOption($name. "[{$key}]")));
			}
		}
	}
    
	private function addNewField($name, $belongsTo, $value, $option){
		switch ($option["type"]){
			case "text":
				$element = new Zend_Form_Element_Text($name);
				break;
			case "select":
				$element = new Zend_Form_Element_Select($name);
				$element->addMultiOptions($this->getSelectOptions($this->getNameOption($belongsTo. "[{$name}]")));
				break;
			default:
				require_once 'Zend/Form/Exception.php';
        		throw new Zend_Form_Exception(sprintf('Element "%s" type was not specified.', $name));
		}
		if (array_key_exists("required", $option)){
			$element->setRequired($option["required"]);
		}
		$element->removeDecorator('DtDdWrapper')->removeDecorator('HtmlTag')->removeDecorator('Label');
		$element->setBelongsTo($belongsTo);
		$element->setValue($value);
		$this->addElement($element);
	}
    
	private function getOption($name){
		if (array_key_exists($name, $this->elements_options)){
			return $this->elements_options[$name];
		}else{
			require_once 'Zend/Form/Exception.php';
        	throw new Zend_Form_Exception(sprintf('Element option "%s" not found.', $name));
		}
	}
	
	private function getNameOption($name){
		return str_replace("]", "",(str_replace("[","_",preg_replace('/([0-9]+)\]\[/','',$name))));
	}
	
	public function hasElement($name){
		if (array_key_exists($name, $this->_elements)) {
            return true;
        }
        return false;
	}
	
}