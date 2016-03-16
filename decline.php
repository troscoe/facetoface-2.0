<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Confirm self registered user.
 *
 * @package    core
 * @subpackage auth
 * @copyright  1999 Martin Dougiamas  http://dougiamas.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//require('../../config.php');

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

global $CFG,$DB;

$data = optional_param('data', '', PARAM_RAW);  // Formatted as:  secret/username

//$PAGE->set_url('/mod/facetoface/approve.php');
//$PAGE->set_context(context_system::instance());

if (!empty($data) || (!empty($p) && !empty($s))) {

    if (!empty($data)) {
        $dataelements     = explode('/', $data, 4); // Stop after 1st slash. Rest is username. MDL-7647
        $usersecret       = $dataelements[0];
        $manager_username = $dataelements[1];
	$s                = $dataelements[2];
	$username         = $dataelements[3];
    } else {
        print_error();
    }
    $manager = get_complete_user_data('username', $manager_username);
    if ($manager->secret == $usersecret) {
        // Load data.
        if (!$session = facetoface_get_session($s)) {
            print_error('error:incorrectcoursemodulesession', 'facetoface');
        }
        if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
            print_error('error:incorrectfacetofaceid', 'facetoface');
        }
        if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
            print_error('error:coursemisconfigured', 'facetoface');
        }
        if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
            print_error('error:incorrectcoursemodule', 'facetoface');
        }

        $attendees = facetoface_get_attendees($session->id);

        $context = context_course::instance($course->id);
        $contextmodule = context_module::instance($cm->id);

	$user = get_complete_user_data('username', $username);

	$form->s = $s;
        $form->requests = array(
            $user->id => "1",
        );

        if (facetoface_approve_requests($form)) {
            //add_to_log($course->id, 'facetoface', 'approve requests', "view.php?id=$cm->id", $facetoface->id, $cm->id);
	    $query =
		"SELECT a.id, a.sessionid, a.userid, b.id, b.signupid, b.statuscode, b.superceded, c.data, d.id, e.data
		FROM {facetoface_signups} a
		INNER JOIN {facetoface_signups_status} b
		ON b.signupid = a.id AND b.superceded=0 AND b.statuscode = 40
		INNER JOIN {user_info_data} c
		ON a.userid = c.userid AND c.fieldid = 8
		INNER JOIN {user} d ON d.email=?
		INNER JOIN {user_info_data} e
		ON e.userid = d.id and e.fieldid = 1
		WHERE c.data = e.data";
	    $params = array ($manager->email);
	    $records =  $DB->get_records_sql($query, $params);
	    if (count($records) < 1) {
	    	$manager->secret = "";
		$DB->update_record('user', $manager);		
	    }
            echo "The training registration request: ".$course->fullname." for ".$user->lastname.", ".$user->firstname." has been declined.";
	}
    } else {
        print_error('invalidconfirmdata');
    }
} else {
    print_error("errorwhenconfirming");
}

//redirect("$CFG->wwwroot/");
