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
 * routines
 * @package    block_resetcompletion
 * @copyright  2016 Andrew Park
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function block_resetcompletion_is_roleswitched() {
    global $USER, $PAGE, $GLOBALS;

    if (\core\session\manager::is_loggedinas()) {
        if (isset($GLOBALS['USER']->realuser)) {
            return true;
        }
    }

    if (!empty($USER->access['rsw']) && is_array($USER->access['rsw'])) {
        if (!empty($PAGE->context) && !empty($USER->access['rsw'][$PAGE->context->path])) {
            return true;
        }
        foreach ($USER->access['rsw'] as $key=>$role) {
            if (strpos($PAGE->context->path,$key)===0) {
                return true;
            }
        }
    }

    // return is_siteadmin();

    return false;
}

// perform the data reset for the specified course/user
function block_resetcompletion_perform_reset($courseid, $userid) {
global $DB, $CFG, $USER;

    require_once($CFG->dirroot . '/lib/gradelib.php');

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $user = \core_user::get_user($userid);

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
            array($courseid, $userid));
    $DB->delete_records('course_completions', array('course' => $courseid, 'userid' => $userid));
    $DB->delete_records('course_completion_crit_compl', array('course' => $courseid, 'userid' => $userid));

    // CHOICE ANSWERS
    if ($dbman->table_exists('choice_answers')) {
        $DB->delete_records_select('choice_answers',
                'choiceid IN (SELECT id FROM mdl_choice WHERE course=?) AND userid=?',
                array($courseid, $userid));
    }

    //SCORM
    if ($dbman->table_exists('scorm_scoes_track')) {
        $DB->delete_records_select('scorm_scoes_track',
                'scormid IN (SELECT id FROM mdl_scorm WHERE course=?) AND userid=?',
                array($courseid, $userid));
    }

    // QUIZ
    if ($dbman->table_exists('quiz')) {
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $orphanedattempts = $DB->get_records_sql_menu("
            SELECT id, uniqueid
              FROM {quiz_attempts}
            WHERE userid=$userid AND quiz IN (SELECT id FROM mdl_quiz WHERE course=$courseid)");

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
                array($courseid, $userid));
    }
    if ($dbman->table_exists('lesson_grades')) {
        $DB->delete_records_select('lesson_grades',
                'lessonid IN (SELECT id FROM mdl_lesson WHERE course=?) AND userid=?',
                array($courseid, $userid));
    }

    // CERTIFICATES
    if ($dbman->table_exists('certificate_issues')) {
        $DB->delete_records_select('certificate_issues',
                'certificateid IN (SELECT id FROM mdl_certificate WHERE course=?) AND userid=?',
                array($courseid, $userid));
    }

    // GRADES
    grade_user_unenrol($courseid, $userid);

    // PURGE CACHE
    cache::make('core', 'completion')->purge();

    $realuser = \core\session\manager::get_realuser();
    $event = \block_resetcompletion\event\reset_completion::create(
        array(
            'userid' => $USER->id, // ME, the person resetting
            'courseid' => $course->id,
            'relateduserid' => $user->id, // THEM, the person being reset
            'context' => context_course::instance($course->id),
            'other' => array('relateduser' => $realuser->username),
        )
    );
    $event->trigger();

}

function block_resetcompletion_find_users($course, $query) {
global $PAGE, $CFG;

    require_once($CFG->dirroot . '/enrol/locallib.php');
    $esky = sesskey();

    $manager = new course_enrolment_manager($PAGE, $course);
    $possible = $manager->search_users($query, true);
    $list = [];
    foreach ($possible['users'] as $user) {
        $a = "/blocks/resetcompletion/reset_user_completion.php?course={$course->id}&sesskey={$esky}&user={$user->id}";
        $lastaccess = userdate($user->lastaccess);
        $list[] = fullname($user) . " &lt;{$user->email}&gt;<br/><small>(Last accessed: $lastaccess)</small><br/>" . html_writer::link($a, get_string('reset'));
    }
    return html_writer::alist($list);

}
