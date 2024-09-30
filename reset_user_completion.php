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
 * Resets course and module completion of a course
 * @package    block_resetcompletion
 * @copyright  2016 Andrew Park, 2022 Tim St Clair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot . '/blocks/resetcompletion/lib.php');
require_once($CFG->dirroot . '/blocks/resetcompletion/classes/event/reset_completion.php');

defined('MOODLE_INTERNAL') || die();

require_sesskey();
$courseid = optional_param('course', 0, PARAM_INT);
$userid = optional_param('user', $USER->id, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/blocks/resetcompletion/reset_user_completion.php', array('course' => $courseid)));
$user = \core_user::get_user($userid);

if (!block_resetcompletion_is_roleswitched() && !is_siteadmin()) {
    die(get_string('notallowed' , 'block_resetcompletion'));
}

if ($confirm) {

    block_resetcompletion_perform_reset($courseid, $userid);
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);

} else {

    $params = [
        'course' => $courseid,
        'confirm' => 1,
        'sesskey' => sesskey(),
        'user' => $userid
    ];

    $strconfirm = get_string('resetconfirm', 'block_resetcompletion');
    $PAGE->set_title($strconfirm);
    $course =  get_course($courseid);
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add($strconfirm);
    echo $OUTPUT->header();
    echo html_writer::tag('p','You are about to permanently delete all stored course data (excluding logs) for the user <b>' . fullname($user) . "</b>. They will have to start over in this course.");
    $buttoncontinue = new single_button(new moodle_url('/blocks/resetcompletion/reset_user_completion.php',
        $params,
        ), get_string('yes'), 'get');
    $buttoncancel = new single_button(new moodle_url('/course/view.php', array('id' => $courseid)), get_string('no'), 'get');
    echo $OUTPUT->confirm(get_string('resetdescription', 'block_resetcompletion'), $buttoncontinue, $buttoncancel);
    echo $OUTPUT->footer();
    exit;
}
