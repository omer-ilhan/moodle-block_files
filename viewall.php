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

require_once('../../config.php');
require_once('../moodleblock.class.php');
require_once('block_files.php');

require_login();
if (isguestuser()) {
    print_error('guestsarenotallowed');
}

global $PAGE, $OUTPUT;

$lastmodifiedon = get_string('last_modified_on', 'block_files');
$blocktitle = get_string('files', 'block_files');
$pagetitle = get_string('all_files', 'block_files');

$url = new moodle_url('/blocks/files/viewall.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($blocktitle);

// Add navigational breadcrumbs to page.
$settingsnode = $PAGE->settingsnav->add($blocktitle);
$editnode = $settingsnode->add($pagetitle, $url);
$editnode->make_active();

// Print the page.
$filesblock = new block_files();
$filesblock->page = $PAGE;
$filesblock->block_files_set_viewall_mode();


echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);
echo $OUTPUT->box_start();
echo $filesblock->get_content()->text;
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
