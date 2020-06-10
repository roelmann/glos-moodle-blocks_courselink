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
 * Main block file.
 *
 * @package    block_courselink
 * @copyright  2019 Richard Oelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
class block_courselink extends block_base {

    public function init() {
        $this->title = get_string('blocktitle', 'block_courselink');
    }

    public function has_config() {
        return false;
    }

    public function applicable_formats() {
        return array('all' => true);
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function hide_header() {
        return true;
    }

    public function get_content() {
        global $COURSE, $DB, $PAGE, $USER, $CFG;

        if (!isloggedin()) {
            return false;
        }
        $pageurl = $PAGE->url;
        if (strpos($pageurl, '/my/') == 0 && strpos($pageurl, '/course/view.php') == 0) {
            return false;
        }

        $this->content = new stdClass;
        $blocktitle = str_replace(" ", "", $this->title);
        $pagelink = new moodle_url ('/blocks/courselink/pages/coursepage.php');

        $crsmapsearch = false;

        if (strlen($USER->institution) > 1) { // If user has course identified.
            $cattree = explode('~', $USER->institution);
            if (empty($cattree[3])) {
                return false;
            }
            $instcode = $cattree[0];
            $schcode = $cattree[1];
            $subjcode = $cattree[2];
            $crscode = 'CRS-'.$cattree[3];
            $course = $DB->get_record('course', array('idnumber' => $crscode), '*', MUST_EXIST);
        } else if (strpos($PAGE->url, 'course') && $PAGE->course->id > 1) { // No user course, but on course page.
            $id = $PAGE->course->id;
            $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
            if (!strpos($course->shortname, 'CRS-DOM')) { // Make sure its a (UoG) course page not a module etc.
                return false;
            }
        } else if (!strpos($USER->email, 'connect.glos.ac.uk')) { // User is not a student.
            $crsmapsearch = true;
        } else { // Catchall.
            return false;
        }

        if ($crsmapsearch) {
            // Get course list.
            $crssql = 'SELECT idnumber, name from {course_categories} WHERE idnumber LIKE "DOM-%" AND visible = 1 ORDER BY name;';
            $crss = $c = '';
            $crss = $DB->get_records_sql($crssql);
            // Create actual block with search box for staff.
            $this->content->text = '<h5 class = "courselinksearch mx-auto text-center text-secondary">';
            $this->content->text .= '<br>Course Map<br>Search<br><br>';
            $this->content->text .= '</h5>';
            $this->content->text .= '<form id="coursesearch" action="'.$pagelink.'" method="post" style="margin:0 0 2px 0">';
            $this->content->text .= '<select name = "crssn">';
            foreach ($crss as $c) {
                $this->content->text .= '<option value = "'.$c->idnumber.'">'.$c->name.'</option>';
            }
            $this->content->text .= '</select>';
            $this->content->text .= '<br>';
            $this->content->text .= '<input type="submit" value="Go">';
            $this->content->text .= '</form>';

        } else {

            if (!isset($course)) { // Catch all - if no course set then return nothing.
                return false;
            }

            // Get course overview files.
            if (empty($CFG->courseoverviewfileslimit)) {
                return array();
            }

            require_once($CFG->libdir. '/filestorage/file_storage.php');
            require_once($CFG->dirroot. '/course/lib.php');
            $fs = get_file_storage();
            $context = context_course::instance($course->id);
            $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', false, 'filename', false);
            if (count($files)) {
                $overviewfilesoptions = course_overviewfiles_options($course->id);
                $acceptedtypes = $overviewfilesoptions['accepted_types'];
                if ($acceptedtypes !== '*') {
                    // Filter only files with allowed extensions.
                    require_once($CFG->libdir. '/filelib.php');
                    foreach ($files as $key => $file) {
                        if (!file_extension_in_typegroup($file->get_filename(), $acceptedtypes)) {
                            unset($files[$key]);
                        }
                    }
                }
                if (count($files) > $CFG->courseoverviewfileslimit) {
                    // Return no more than $CFG->courseoverviewfileslimit files.
                    $files = array_slice($files, 0, $CFG->courseoverviewfileslimit, true);
                }
            }

            // Get course overview files as images - set $courseimage.
            // The loop means that the LAST stored image will be the one displayed if >1 image file.
            $courseimage = '';
            foreach ($files as $file) {
                $isimage = $file->is_valid_image();
                if ($isimage) {
                    $courseimage = file_encode_url("$CFG->wwwroot/pluginfile.php",
                        '/'. $file->get_contextid(). '/'. $file->get_component(). '/'.
                        $file->get_filearea(). $file->get_filepath(). $file->get_filename(), !$isimage);
                }
            }

            // Create actual block with image and text - for single link.
            $this->content->text = '<div>';
            if (isset($courseimage)) {
                $this->content->text .= '<img src="'.$courseimage.'">';
            }
            $this->content->text .= '</div>';
            $this->content->text .= '<h5 class = "courselinktext">';
            $this->content->text .= '<form id="courselink" action="'.$pagelink.'" method="post" style="margin:0 0 2px 0">';
            $this->content->text .= '<input type="hidden" name="crssn" value="'.$course->shortname.'">';
            $this->content->text .= '<input type="submit" value="Course Map" class="courselinksubmit p-2" >';
            $this->content->text .= '</form>';
            $this->content->text .= '</h5>';

        }

        return $this->content;
    }

    public function specialization() {
        if (isset($this->config)) {
            if (empty($this->config->title)) {
                $this->title = get_string('blocktitle', 'block_courselink');
            } else {
                $this->title = $this->config->title;
            }

            if (empty($this->config->text)) {
                $this->config->text = get_string('blocktext', 'block_courselink');
            }
        }
    }

    public function instance_config_save($data, $nolongerused = false) {
        if (get_config('courselink', 'Allow_HTML') == '1') {
            $data->text = strip_tags($data->text);
        }

        // And now forward to the default implementation defined in the parent class.
        return parent::instance_config_save($data, $nolongerused);
    }

    public function html_attributes() {
        $attributes = parent::html_attributes(); // Get default values.
        $attributes['class'] .= ' block_'. $this->name(); // Append our class to class attribute.
        $attributes['class'] .= ' block_'. $this->title; // Append our class to class attribute.
        return $attributes;
    }
}