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
 * Displays the reset block
 * @package    block_resetcompletion
 * @copyright  2016 Andrew Park
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot . '/blocks/resetcompletion/lib.php');

class block_resetcompletion extends block_base {
    public function init() {
        $this->title = get_string('resetcompletion', 'block_resetcompletion');
    }

    public function get_content() {
        global $CFG, $USER, $PAGE, $OUTPUT;

        $force = optional_param('force',0,PARAM_INT);
        $q = optional_param('q', '', PARAM_TEXT);

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $userid = $USER->id;

        if (block_resetcompletion_is_roleswitched()) {

            $info = new completion_info($this->page->course);

            if (!$info->is_tracked_user($userid)) {
                $this->content->text = get_string('unenrolled', 'block_resetcompletion');
                return $this->content;
            }

            if ($info->is_course_complete($userid) || $force) {
                $this->content->text = get_string('resetcontenttext', 'block_resetcompletion');
                $this->content->footer = '<br/><a href="' . $CFG->wwwroot . '/blocks/resetcompletion/reset_user_completion.php?course=' .
                    $this->page->course->id .
                    '&sesskey=' . sesskey() .
                    '&user=' . $userid;
                $this->content->footer .= '">' . get_string('pluginname', 'block_resetcompletion')  . '</a>';
                return $this->content;
            } else {

                $forceurl = new moodle_url($PAGE->url);
                $forceurl->param('force', 1);

                $this->content->text = get_string('resetincompletetext', 'block_resetcompletion', $forceurl->out());
                return $this->content;
            }

        } else if (is_siteadmin()) {

            $html = '';

            $data = [
                'action' => new moodle_url($PAGE->url),
                'inputname' => 'q',
                'searchstring' => get_string('search'),
                'hiddenfields' => [],
                'query' => $q,
            ];

            // discover the URL parameters for the current page
            $params = [];
            parse_str(http_build_query($_GET), $params);
            foreach ($params as $k => $v) {
                $data['hiddenfields'][] = (object) ['name' => $k, 'value' => $v];
            }

            if ($this->page->context && $this->page->context->contextlevel !== CONTEXT_SYSTEM) {
                $data['hiddenfields'][] = (object) ['name' => 'context', 'value' => $this->page->context->id];
            }

            // search for ALWAYS uses GET, so hidden fields replace the action parameters
            $html .= $OUTPUT->render_from_template('core/search_input', $data);

            if (!empty($q)) {
                $html .= block_resetcompletion_find_users($this->page->course, $q);
            }

            $this->content->text = $html;

        }

        return $this->content;
    }
}


