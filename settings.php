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

$settings->add(new admin_setting_heading(
    'headerconfig',
    get_string('headerconfig', 'block_files'),
    get_string('descconfig', 'block_files')
));

$settings->add(new admin_setting_configselect(
    'block_files/current_months',
    get_string('labelcurrentmonths', 'block_files'),
    get_string('desccurrentmonths', 'block_files'),
    3,
    array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12)
));