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

defined('MOODLE_INTERNAL') || die();

require_sesskey();
$courseid = optional_param('course', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/blocks/resetcompletion/reset_user_completion.php', array('course' => $courseid)));
$user = $USER->id;
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
global $DB;

if (!block_resetcompletion_is_roleswitched()) {
    die(get_string('notallowed' , 'block_resetcompletion'));
}

if ($confirm) {
    $completion = new completion_info($course);
    if (!$completion->is_enabled()) {
        throw new moodle_exception('completionnotenabled', 'completion');
    } else if (!$completion->is_tracked_user($user)) {
        throw new moodle_exception('nottracked', 'completion');
    }
    
    $dbman = $DB->get_manager();

    // COMPLETION DATA
    $DB->delete_records_select('course_modules_completion',
            'coursemoduleid IN (SELECT id FROM mdl_course_modules WHERE course=?) AND userid=?',
            array($courseid, $user));
    $DB->delete_records('course_completions', array('course' => $courseid, 'userid' => $user));
    $DB->delete_records('course_completion_crit_compl', array('course' => $courseid, 'userid' => $user));

    // CHOICE ANSWERS
    if ($dbman->table_exists('choice_answers')) {
        $DB->delete_records_select('choice_answers',
                'choiceid IN (SELECT id FROM mdl_choice WHERE course=?) AND userid=?',
                array($courseid, $user));
    }

    //SCORM
    if ($dbman->table_exists('scorm_scoes_track')) {
        $DB->delete_records_select('scorm_scoes_track',
                'scormid IN (SELECT id FROM mdl_scorm WHERE course=?) AND userid=?',
                array($courseid, $user));
    }

    // QUIZ
    if ($dbman->table_exists('quiz')) {
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $orphanedattempts = $DB->get_records_sql_menu("
            SELECT id, uniqueid
              FROM {quiz_attempts}
            WHERE userid=$user AND quiz IN (SELECT id FROM mdl_quiz WHERE course=$courseid)");

        if ($orphanedattempts) {
            foreach ($orphanedattempts as $attemptid => $usageid) {
                question_engine::delete_questions_usage_by_activity($usageid);
                $DB->delete_records('quiz_attempts', array('id' => $attemptid));
            }
        }
    }

    // LESSONS
    if ($dbman->table_exists('lesson_attempts')) {
        $DB->delete_records_select('lesson_attempts',
                'lessonid IN (SELECT id FROM mdl_lesson WHERE course=?) AND userid=?',
                array($courseid, $user));
    }
    if ($dbman->table_exists('lesson_grades')) {
        $DB->delete_records_select('lesson_grades',
                'lessonid IN (SELECT id FROM mdl_lesson WHERE course=?) AND userid=?',
                array($courseid, $user));
    }

    // CERTIFICATES
    if ($dbman->table_exists('certificate_issues')) {
        $DB->delete_records_select('certificate_issues',
                'certificateid IN (SELECT id FROM mdl_certificate WHERE course=?) AND userid=?',
                array($courseid, $user));
    }

    cache::make('core', 'completion')->purge();
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);

} else {
    $strconfirm = get_string('resetconfirm', 'block_resetcompletion');
    $PAGE->set_title($strconfirm);
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add($strconfirm);
    echo $OUTPUT->header();
    $buttoncontinue = new single_button(new moodle_url('/blocks/resetcompletion/reset_user_completion.php',
        array('course' => $courseid, 'confirm' => 1, 'sesskey' => sesskey())), get_string('yes'), 'get');
    $buttoncancel = new single_button(new moodle_url('/course/view.php', array('id' => $courseid)), get_string('no'), 'get');
    echo $OUTPUT->confirm(get_string('resetdescription', 'block_resetcompletion'), $buttoncontinue, $buttoncancel);
    echo $OUTPUT->footer();
    exit;
}
