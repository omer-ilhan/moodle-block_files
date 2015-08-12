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

class block_files_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader', get_string('files_config_header', 'block_files'));
        $mform->addElement('text', 'config_elements_to_display', get_string('files_config_number_displayed_files', 'block_files'));
        $mform->setDefault('config_elements_to_display', 5);
        $mform->setType('config_elements_to_display', PARAM_INT);
    }

}
