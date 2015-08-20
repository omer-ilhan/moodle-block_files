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
 * @package   block_files
 * @copyright 2015, Iwailo Denisow, Ömer Malik Ilhan
 * @license   GNU General Public License <http://www.gnu.org/licenses/>
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class block_files
 * Main class of the recent files block.
 * Displays a block containing a number of the most recently updated or modified
 * files and folders from all enrolled courses.
 */
class block_files extends block_base {

    /**
     * This is the default amount of elements to display, used when nothing else is configured or not on viewall page.
     */
    const DEFAULT_ELEMENTS_TO_DISPLAY = 5;

    /**
     * This little javascript snippet is used to override the fallback link to the viewall page and display
     * the surplus (if count(items) > elements_to_display) items on the same page instead.
     */
    const MORE_BTN_JS = 'this.style.display="none";var elements=document.querySelectorAll(".block-files-item-hidden");var length=elements.length;for(var i=0;i<length;i++){var display=elements[i].style.display;elements[i].style.display="table-row"}return false;';

    /**
     * This little javascript snippet is used to hide the the surplus items again.
     */
    const LESS_BTN_JS = 'this.style.display="none";var elements=document.querySelectorAll(".block-files-item-hidden");var length=elements.length;for(var i=0;i<length;i++){elements[i].style.display="none"}var morebtn=document.getElementById("block-files-show-more-button");if(morebtn.style.display=="none"){morebtn.style.display=""}return false;';

    /**
     * @var bool value determining whether to show all files or not, used on the viewall page.
     */
    private $viewall;

    public function init() {
        $this->title = get_string('files', 'block_files');
        $this->viewall = false;
    }

    /**
     * Set the viewall property to true.
     */
    public function block_files_set_viewall_mode() {
        $this->viewall = true;
    }

    public function get_content() {
        global $CFG, $USER;
        if ($this->content !== null) {
            return $this->content;
        }

        // Required to use course_get_url() reliably.
        require_once($CFG->dirroot . '/course/lib.php');

        $this->content = new stdClass;
        $userid = $USER->id;
        if ($userid == 0) {
            return $this->content;
        }

        $this->content->text = '';

        $itemtoremove = optional_param('remove_pinned_item', -1, PARAM_INT);
        $itemtoadd = optional_param('add_pinned_item', -1, PARAM_INT);
        $this->unpin_item($userid, $itemtoremove);
        $this->pin_item($userid, $itemtoadd);

        // Get all courses the user is enrolled in, filter out old ones.
        $enrolledcourses = array_filter(
            enrol_get_my_courses(array('id', 'fullname', 'shortname', 'startdate', 'format'), 'startdate DESC, sortorder ASC'),   array(&$this, 'is_current'));

        // Get information about all relevant file items in all courses, e.g. files and folders.
        $pinneditems = $this->get_pinned_file_items($userid);
        $fileitems = $this->get_recent_file_items($enrolledcourses, array(&$this, 'compare_time_modified'));

        $elementstodisplay = self::DEFAULT_ELEMENTS_TO_DISPLAY;

        if (!$this->viewall) {
            if (isset($this->config->elements_to_display)) {
                $elementstodisplay = $this->config->elements_to_display;
            }
            $elementstodisplay = (count($pinneditems) > $elementstodisplay) ? count($pinneditems) : $elementstodisplay;
        } else {
            $elementstodisplay = count($fileitems);
        }

        $this->content->text .= $this->create_content_recent($fileitems, $elementstodisplay);
        $this->content->text .= $this->create_content_pinned($pinneditems);
        $this->content->footer = $this->create_content_footer(count($fileitems) - $elementstodisplay);

        // Necessary to render both tables side-by-side.
        $this->content->text = html_writer::div($this->content->text, 'row-fluid');

        return $this->content;
    }

    /**
     * Pin an item for the user, i.e. save it to the database.
     *
     * @param $userid int id of the user user pinning an item
     * @param $cmid int id of the item to pin
     */
    private function pin_item($userid, $cmid) {
        if ($cmid === -1) {
            return;
        }
        global $DB;
        $record = new stdClass();
        $record->userid = $userid;
        $record->cmid = $cmid;

        try {
            $DB->insert_record('block_files', $record, false);
        } catch (Exception $e) {
            // TODO log it maybe, idk
            // happens when the user wants to add an already existing item to the table
            // since that violates the unique constraint on (user, fileitem).
        }
    }

    /**
     * Unpin an item for a user, i.e. delete it from the database.
     *
     * @param $userid int id of the user unpinning an item
     * @param $cmid int id of the item to unpin
     */
    private function unpin_item($userid, $cmid) {
        if ($cmid === -1) {
            return;
        }
        global $DB;
        $DB->delete_records('block_files', array('cmid' => $cmid, 'userid' => $userid));
    }

    /**
     * Convert given timestamp into a readable format.
     *
     * @param $time int timestamp
     * @return string time converted into a readable format
     */
    private function format_time($time) {
        return userdate($time, '%d.%m.%y %H:%M');
    }

    /**
     * Check if a given course is current.
     *
     * @param $course course to check
     * @return bool true if the course started within 7 months from today, false otherwise
     */
    private function is_current($course) {
        $mincurrenttime = strtotime('first day of -7 month');
        return $course->startdate >= $mincurrenttime;
    }

    /**
     * Comparison function for the timemodified value, used for sorting (by most recent).
     *
     * @param $a first item
     * @param $b second item
     * @return int zero if they are the same, negative value if $b is older, positive value otherwise
     */
    private function compare_time_modified($a, $b) {
        return $b['timemodified'] - $a['timemodified'];
    }

    /**
     * Create properly formatted table items for the block_files table from given fileitems.
     *
     * @param array $fileitems contains fileitems
     * @param bool|false $pinnedfilestable value determining whether the items are to be used in the pinned files table
     * @return array contains properly formatted table items
     */
    private function create_table_items(array $fileitems, $pinnedfilestable = false) {
        $lastmodifiedon = get_string('last_modified_on', 'block_files');
        $tableitems = array();
        foreach ($fileitems as $fileitem) {
            $fileicon = html_writer::img($fileitem['iconurl'], 'icon');

            $tooltip = $lastmodifiedon . $this->format_time($fileitem['timemodified']);
            $filelink = html_writer::link($fileitem['fileurl'], $fileicon . $fileitem['filename'], array( 'title' => $tooltip));

            $courselink = html_writer::link($fileitem['courseurl'], $fileitem['courseshortname'], array( 'title' => $fileitem['coursename']));
            if ($pinnedfilestable) {
                $buttonurl = new moodle_url($this->page->url, array('remove_pinned_item' => $fileitem['cm_id']));
                $pinbutton = html_writer::link($buttonurl, html_writer::tag('i', "",
                    array(
                        'class' => 'icon-star',
                        'title' => get_string('unpin_item', 'block_files'),
                        'onmouseover' => 'this.className = "icon-star-empty";',
                        'onmouseout' => 'this.className = "icon-star";'
                    )));
            } else {
                $buttonurl = new moodle_url($this->page->url, array('add_pinned_item' => $fileitem['cm_id']));
                $pinbutton = html_writer::link($buttonurl, html_writer::tag('i', "",
                    array(
                        'class' => 'icon-star-empty',
                        'title' => get_string('pin_item', 'block_files'),
                        'onmouseover' => 'this.className = "icon-star";',
                        'onmouseout' => 'this.className = "icon-star-empty";'
                    )));
            }
            $tableitems[] = array($filelink, $courselink, $pinbutton);
        }
        return $tableitems;
    }

    /**
     * Retrieve user's pinned file items from the database.
     *
     * @param $userid int id of the user
     * @return array pinned file items
     */
    private function get_pinned_file_items($userid) {
        global $DB;
        $fs = get_file_storage();
        $pinneditemids = $DB->get_records('block_files', array('userid' => $userid), 'id', 'cmid');
        $pinneditems = array();
        foreach ($pinneditemids as $pinneditemid) {
            $cmid = $pinneditemid->cmid;
            $cminfo = $DB->get_record('course_modules', array('id' => $cmid), 'course');
            $courseid = $cminfo->course;
            $course = get_course($courseid);
            $newpinneditem = $this->get_file_items($fs, $course, $cmid);
            $pinneditems = array_merge($pinneditems, $newpinneditem);
        }
        return $pinneditems;
    }

    /**
     * Retrieve all file items, i.e. files and folders, or one specified file item from course.
     *
     * @param $fs file_storage filesystem
     * @param $course the course
     * @param null $cmid int course module id to extract file item from (only one)
     * @return array retrieved file items
     */
    private function get_file_items($fs, $course, $cmid = null) {
        $coursename = $course->fullname;
        $courseshortname = $course->shortname;
        $courseurl = course_get_url($course);
        $cms = $cmid === null ? get_fast_modinfo($course)->get_cms() : array(get_fast_modinfo($course)->get_cm($cmid));

        $fileitems = array();
        foreach ($cms as $cm) {
            if ($cm->is_user_access_restricted_by_capability()) {
                continue;
            }
            $cmtype = $cm->modname;
            if ($cmtype === 'folder') {
                // use the most recently modified file..
                $cmfiles = $fs->get_area_files($cm->context->id, 'mod_folder', 'content', false, 'timemodified', false);
                $cmfile = array_pop($cmfiles);
                if (isset($cmfile)) {
                    $fileitems[] = $this->create_file_item($coursename, $courseshortname, $courseurl, $cm, $cmfile);
                }
            } else if ($cmtype === 'resource') { // check if resource === file.
                $cmfiles = $fs->get_area_files($cm->context->id, 'mod_resource', 'content', false, 'timemodified', false);
                foreach ($cmfiles as $cmfile) {
                    $fileitems[] = $this->create_file_item($coursename, $courseshortname, $courseurl, $cm, $cmfile);
                }
            }
        }
        return $fileitems;
    }

    /**
     * Retrieve recent file items.
     *
     * @param array $courses courses the user is enrolled in
     * @param null $usortcallback method for sorting (if any)
     * @return array (sorted) file items
     */
    private function get_recent_file_items(array $courses, $usortcallback = null) {
        $fs = get_file_storage();
        $fileitems = array();
        foreach ($courses as $course) {
            $newfileitems = $this->get_file_items($fs, $course);
            $fileitems = array_merge($fileitems, $newfileitems);
        }
        if (isset($usortcallback)) {
            usort($fileitems, $usortcallback);
        }
        return $fileitems;
    }

    /**
     * Create a file item.
     *
     * @param $coursename string name of the course this file item belongs to
     * @param $courseshortname string short name of the course this file item belongs to
     * @param $courseurl url of the course this file item belongs to
     * @param $cm course module this file item belongs to
     * @param $cmfile the actual file
     * @return array describing a file item, used to create table items
     */
    private function create_file_item($coursename, $courseshortname, $courseurl, $cm, $cmfile) {
        return array(
            'cm_id' => $cm->id,
            'iconurl' => $cm->get_icon_url(),
            'fileurl' => $cm->context->get_url(),
            'filename' => $cm->get_formatted_name(),
            'courseurl' => $courseurl,
            'coursename' => $coursename,
            'courseshortname' => $courseshortname,
            'timemodified' => $cmfile->get_timemodified()
        );
    }

    /**
     * Create a block_files table with given name.
     *
     * @param $tablename string name of the table
     * @return html_table block_files table
     */
    private function create_table($tablename) {
        $table = new html_table();
        $table->attributes = array('class' => 'table table-responsive table-striped table-hover table-bordered');
        $th = new html_table_cell($tablename);
        $th->header = true;
        $th->colspan = 3;
        $th->attributes = array('class' => 'label label-important block-files-th');
        $table->head = array($th);
        return $table;
    }

    /**
     * Create block_files table containing pinned file items.
     *
     * @param array $pinneditems pinned file items
     * @return string html table ready for presentation
     */
    private function create_content_pinned(array $pinneditems) {
        $table = $this->create_table(get_string('pinned_files', 'block_files'));
        if (empty($pinneditems)) {
            $table->data[] = array(get_string('no_files', 'block_files'));
        } else {
            $table->data = $this->create_table_items($pinneditems, true);
        }

        return html_writer::div(html_writer::table($table), 'span6 block-files-table');
    }

    /**
     * Create block_files table containing recent file items.
     *
     * @param array $fileitems recent file items
     * @param $showcount int number of visible file items
     * @return string html table ready for presentation
     */
    private function create_content_recent(array $fileitems, $showcount) {
        $table = $this->create_table(get_string('recent_files', 'block_files'));
        $table->rowclasses = array();
        $itemsize = count($fileitems);
        for ($i = $showcount; $i < $itemsize; $i++) {
            $table->rowclasses[$i] = 'block-files-item-hidden';
        }
        if (empty($fileitems)) {
            $table->data[] = array(get_string('no_files', 'block_files'));
        } else {
            $table->data = $this->create_table_items($fileitems);
        }

        return html_writer::div(html_writer::table($table), 'span6 block-files-table');
    }

    /**
     * Create block_files footer. It either contains a link to view surplus items (if any) or nothing.
     *
     * @param $surplusitemscount int number of surplus recent file items
     * @return string empty string or footer with link
     */
    private function create_content_footer($surplusitemscount) {
        if ($surplusitemscount <= 0) {
            return '';
        }

        $morebtnstring = sprintf(get_string('show_more', 'block_files'), $surplusitemscount);
        $morebtnlink = new moodle_url('/blocks/files/viewall.php');
        $morebtn = html_writer::link($morebtnlink, $morebtnstring, array('id' => 'block-files-show-more-button', 'onclick' => block_files::MORE_BTN_JS ));
        $lessbtn = html_writer::link('#', get_string('show_less', 'block_files'), array( 'class' => 'block-files-item-hidden', 'onclick' => block_files::LESS_BTN_JS ));
        $footer = $morebtn . $lessbtn;
        
        return $footer;
    }
}
