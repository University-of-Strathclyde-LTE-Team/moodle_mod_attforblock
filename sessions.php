<?PHP // $Id: sessions.php,v 1.2.2.3 2009/02/23 19:22:41 dlnsk Exp $

    require_once('../../config.php');    
	require_once($CFG->libdir.'/blocklib.php');
	require_once('locallib.php');	
	require_once('lib.php');
	require_once('add_form.php');
	require_once('update_form.php');
	require_once('duration_form.php');
	
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }
    
    $id   	= required_param('id', PARAM_INT);
    $action = required_param('action', PARAM_ACTION);
    
    if ($id) {
        if (! $cm = $DB->get_record('course_modules', array('id' => $id))) {
            print_error('Course Module ID was incorrect');
        }
        if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
            print_error('Course is misconfigured');
        }
	    if (! $attforblock = $DB->get_record('attforblock', array('id' => $cm->instance))) {
	        print_error("Course module is incorrect");
	    }
    }

    require_login($course->id);

    if (! $user = $DB->get_record('user', array('id' => $USER->id)) ) {
        print_error("No such user in this course");
    }
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }
    
    require_capability('mod/attforblock:manageattendances', $context);

    $navlinks[] = array('name' => $attforblock->name, 'link' => "view.php?id=$id", 'type' => 'activity');
    $navlinks[] = array('name' => get_string($action, 'attforblock'), 'link' => null, 'type' => 'activityinstance');
    print_header("$course->shortname: ".$attforblock->name.' - '.get_string($action,'attforblock'), $course->fullname,
                 $navlinks, "", "", true, "&nbsp;", navmenu($course));

	//////////////////////////////////////////////////////////
	// Adding sessions
	//////////////////////////////////////////////////////////
	    
	if ($action === 'add') {
		
		show_tabs($cm, $context, 'add');
		$mform_add = new mod_attforblock_add_form('sessions.php', array('course'=>$course, 'cm'=>$cm, 'modcontext'=>$context));
	    
	    if ($fromform = $mform_add->get_data()) {
			$duration = $fromform->durtime['hours']*HOURSECS + $fromform->durtime['minutes']*MINSECS;
			$now = time();
			
	    	if (isset($fromform->addmultiply)) {
		    	$startdate = $fromform->sessiondate;// + $fromform->stime['hour']*3600 + $fromform->stime['minute']*60;
				$enddate = $fromform->sessionenddate + ONE_DAY; // because enddate in 0:0am
				
				//get number of days
				$days = (int)ceil(($enddate - $startdate) / ONE_DAY);
				if($days <= 0)
					print_error(get_string('wrongdatesselected','attforblock'), "sessions.php?id=$id&amp;action=add");
				else {
					add_to_log($course->id, 'attendance', 'multiply sessions added', 'mod/attforblock/manage.php?id='.$id, $user->lastname.' '.$user->firstname);
		
					// Getting first day of week
					$sdate = $startdate;
					$dinfo = usergetdate($sdate);
					if ($CFG->calendar_startwday === '0') { //week start from sunday
						$startweek = $startdate - $dinfo['wday'] * ONE_DAY; //call new variable
					} else {
						$wday = $dinfo['wday'] === 0 ? 7 : $dinfo['wday'];
						$startweek = $startdate - ($wday-1) * ONE_DAY;
					}
					// Adding sessions
					$wdaydesc = array(0=>'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
					while ($sdate < $enddate) {
						if($sdate < $startweek + ONE_WEEK) {
							$dinfo = usergetdate($sdate);
							if(key_exists($wdaydesc[$dinfo['wday']] ,$fromform->sdays)) {
								//check whether this date there is in our session days
//								if(count_records('attendance_sessions', 'courseid', $course->id, 'sessdate', $sdate) > 0) {
//									notify(strftime(get_string('strftimedmy', 'attforblock'), $sdate).': '.get_string('sessionexist','attforblock'));
//									$sdate += ONE_DAY;
//									continue;
//								}
								$rec->courseid = $course->id;
								$rec->sessdate = $sdate;
								$rec->duration = $duration;
								$rec->description = $fromform->sdescription;
								$rec->timemodified = $now;
								if(!$DB->insert_record('attendance_sessions', $rec))
									print_error(get_string('erroringeneratingsessions','attforblock'), "sessions.php?id=$id&amp;action=add");
							}
							$sdate += ONE_DAY;
						} else {
							$startweek += ONE_WEEK * $fromform->period;
							$sdate = $startweek;
						}
					}
					notice(get_string('sessionsgenerated','attforblock'));
				}	    		
	    	} else {
				// insert one session
				$rec->courseid = $course->id;
				$rec->sessdate = $fromform->sessiondate;
				$rec->duration = $duration;
				$rec->description = $fromform->sdescription;
				$rec->timemodified = $now;
				if($DB->insert_record('attendance_sessions', $rec)) {
					add_to_log($course->id, 'attendance', 'one session added', 'mod/attforblock/manage.php?id='.$id, $user->lastname.' '.$user->firstname);
					notice(get_string('sessionadded','attforblock'), "sessions.php?id=$id&amp;action=add");
				} else
					print_error(get_string('errorinaddingsession','attforblock'), "sessions.php?id=$id&amp;action=add");
	    	}
	    }
		$mform_add->display();
	}
	
	//////////////////////////////////////////////////////////
	// Updating sessions
	//////////////////////////////////////////////////////////

	if ($action === 'update') {
		
		$sessionid	= required_param('sessionid');
		$mform_update = new mod_attforblock_update_form('sessions.php', array('course'=>$course, 
																			  'cm'=>$cm, 
																			  'modcontext'=>$context,
																			  'sessionid'=>$sessionid));
	    if ($mform_update->is_cancelled()) {
	    	redirect('manage.php?id='.$id);
	    }
	    if ($fromform = $mform_update->get_data()) {
		    if (!$att = $DB->get_record('attendance_sessions', array('id' => $sessionid)) ) {
		        print_error('No such session in this course');
		    }
		
//			$newdate = mktime($newhour, $newminute, 0, $newmonth, $newday, $newyear); //new variables called here
		
			//check for duplicate
//			$count = count_records('attendance_sessions','courseid', $course->id, 'sessdate', $newdate);
//			if($count != 0 && $newdate != $att->sessdate)		//duplicate session exists
//			    error(get_string('sessionalreadyexists','attforblock'),"sessions.php?id=".$id."&amp;sessionid=".
//		            $sessionid."&amp;sessdate=".$att->sessdate."&amp;action=update");	
//			else
//			{	
				//update session
				$att->sessdate = $fromform->sessiondate;
				$att->duration = $fromform->durtime['hours']*HOURSECS + $fromform->durtime['minutes']*MINSECS;
				$att->description = $fromform->sdescription;
				$att->timemodified = time();
				$DB->update_record('attendance_sessions', $att);
				add_to_log($course->id, 'attendance', 'Session updated', 'mod/attforblock/manage.php?id='.$id, $user->lastname.' '.$user->firstname);
				//notice(get_string('sessionupdated','attforblock'), 'manage.php?id='.$id);
				redirect('manage.php?id='.$id, get_string('sessionupdated','attforblock'), 3);
//			}
		}
	
		echo $OUTPUT->heading(get_string('update','attforblock').' ' .get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
		$mform_update->display();
	}
	
	//////////////////////////////////////////////////////////
	// Deleting sessions
	//////////////////////////////////////////////////////////

	if ($action === 'delete') {
		$sessionid	 = required_param('sessionid');
		$confirm = optional_param('confirm');

	    if (!$att = $DB->get_record('attendance_sessions', array('id' => $sessionid)) ) {
	        print_error('No such session in this course');
	    }
	    
		if (isset($confirm)) {
			$DB->delete_records('attendance_log', array('sessionid' => $sessionid));
			$DB->delete_records('attendance_sessions', array('id' => $sessionid));
			add_to_log($course->id, 'attendance', 'Session deleted', 'mod/attforblock/manage.php?id='.$id, $user->lastname.' '.$user->firstname);
			$attforblockrecord = $DB->get_record('attforblock', array('course' => $course->id));
            attforblock_update_grades($attforblockrecord);
			redirect('manage.php?id='.$id, get_string('sessiondeleted','attforblock'), 3);
		}
		
		echo $OUTPUT->heading(get_string('deletingsession','attforblock').' :: ' .$course->fullname);
	
		echo $OUTPUT->confirm(get_string('deletecheckfull', '', get_string('session', 'attforblock')).
			             '<br /><br />'.userdate($att->sessdate, get_string('str_ftimedmyhm', 'attforblock')).': '.
			             ($att->description ? $att->description : get_string('nodescription', 'attforblock')),
	                     "sessions.php?id=$id&amp;sessionid=$sessionid&amp;action=delete&amp;confirm=1", $_SERVER['HTTP_REFERER']);
	}
	
	if ($action === 'deleteselected') {
		$confirm = optional_param('confirm');
		if (isset($confirm)) {
			$sessionid = required_param('sessionid');
			$ids = implode(',', explode('_', $sessionid));
			$DB->delete_records_select('attendance_log', "sessionid", array($ids));
			$DB->delete_records_select('attendance_sessions', "id", array($ids));
			add_to_log($course->id, 'attendance', 'Several sessions deleted', 'mod/attforblock/manage.php?id='.$id, $user->lastname.' '.$user->firstname);
			
			$attforblockrecord = $DB->get_record('attforblock', array('course' => $course->id));
			attforblock_update_grades($attforblockrecord);
			redirect('manage.php?id='.$id, get_string('sessiondeleted','attforblock'), 3);
		}
		
		$fromform = data_submitted();
		$slist = implode(',', array_keys($fromform->sessid));
		$sessions = $DB->get_records_list('attendance_sessions', array('id' => $slist), 'sessdate');
		
		echo $OUTPUT->heading(get_string('deletingsession','attforblock').' :: ' .$course->fullname);
		$message = '<br />';
		foreach ($sessions as $att) {
			$message .= '<br />'.userdate($att->sessdate, get_string('str_ftimedmyhm', 'attforblock')).': '.
			             ($att->description ? $att->description : get_string('nodescription', 'attforblock'));
		}
		
		$slist = implode('_', array_keys($fromform->sessid));
		echo $OUTPUT->confirm(get_string('deletecheckfull', '', get_string('sessions', 'attforblock')).$message,
	                     "sessions.php?id=$id&amp;sessionid=$slist&amp;action=deleteselected&amp;confirm=1", $_SERVER['HTTP_REFERER']);
	}

	//////////////////////////////////////////////////////////
	// Change duration
	//////////////////////////////////////////////////////////
	
	if ($action === 'changeduration') {
		$fromform = data_submitted();
		$slist = isset($fromform->sessid) ? implode('_', array_keys($fromform->sessid)) : '';
		
		$mform_duration = new mod_attforblock_duration_form('sessions.php', array('course'=>$course, 
																  'cm'=>$cm, 
																  'modcontext'=>$context,
																  'ids'=>$slist));
	    if ($mform_duration->is_cancelled()) {
	    	redirect('manage.php?id='.$id);
	    }
	    if ($fromform = $mform_duration->get_data()) {
	    	$now = time();
	    	//$slist = implode(',', explode('_', $fromform->ids));
	    	$slist = explode('_', $fromform->ids);
	    	if (!$sessions = $DB->get_records_list('attendance_sessions', 'id', $slist) ) {
		        print_error('No such session in this course');
		    }
			foreach ($sessions as $sess) {
				$sess->duration = $fromform->durtime['hours']*HOURSECS + $fromform->durtime['minutes']*MINSECS;
				$sess->timemodified = $now;
				$DB->update_record('attendance_sessions', $sess);
			}
			add_to_log($course->id, 'attendance', 'Session updated', 'mod/attforblock/manage.php?id='.$id, $user->lastname.' '.$user->firstname);
			redirect('manage.php?id='.$id, get_string('sessionupdated','attforblock'), 3);
		}
		echo $OUTPUT->heading(get_string('update','attforblock').' ' .get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
		$mform_duration->display();
		
	}
		
	echo $OUTPUT->footer($course);
	
	?>	
