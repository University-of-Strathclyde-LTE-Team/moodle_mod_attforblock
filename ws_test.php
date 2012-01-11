<?php

class mod_attforblock_get_attendance_data_form extends moodleform{
	
	public function definition() {
		global $CFG;
		$mform = $this->_form;
		
		$mform->addElement('header','wstestclienthdr',get_string('testclient','webservice'));
		
	  $data = $this->_customdata;
        if ($data['authmethod'] == 'simple') {
            $mform->addElement('text', 'wsusername', 'wsusername');
            $mform->addElement('text', 'wspassword', 'wspassword');
        } else  if ($data['authmethod'] == 'token') {
            $mform->addElement('text', 'token', 'token');
        }
		
                $mform->addElement('hidden', 'authmethod', $data['authmethod']);
        $mform->setType('authmethod', PARAM_SAFEDIR);
        $mform->addElement('text', 'attendanceid', 'attendanceid');

        $mform->addElement('hidden', 'function');
        $mform->setType('function', PARAM_SAFEDIR);

        $mform->addElement('hidden', 'protocol');
        $mform->setType('protocol', PARAM_SAFEDIR);
		
		$this->add_action_buttons(true,get_string('execute','webservice'));
	}
	
	public function get_params() {
		$params = array();
        if (!$data = $this->get_data()) {
            return null;
        }
                // remove unused from form data
        unset($data->submitbutton);
        unset($data->protocol);
        unset($data->function);
        unset($data->wsusername);
        unset($data->wspassword);
        unset($data->token);
        unset($data->authmethod);
        
        $params['attendanceid']=1;
        return $params;
	}
	
}