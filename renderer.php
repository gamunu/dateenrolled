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
 * Renderer for outputting the dateenrolled course format.
 *
 * @package format_dateenrolled
 * @copyright 2014 Gamunu Bandara
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.4
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/renderer.php');
require_once($CFG->dirroot . '/course/format/dateenrolled/lib.php');


/**
 * Basic renderer for dateenrolled format.
 *
 * @copyright 2014 Gamunu Bandara
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_dateenrolled_renderer extends format_section_renderer_base
{
    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list()
    {
        return html_writer::start_tag('ul', array('class' => 'dateenrolled'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list()
    {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title()
    {
        return get_string('weeklyoutline');
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused)
    {
        global $PAGE;

        $courserenderer = $PAGE->get_renderer('core', 'course');
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();
        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);

        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();
        $allsections = $modinfo->get_section_info_all();
        //only require 0 + number of sections in course settings
        /// $requiredsections = array_slice($allsections, 0, $course->numsections + 1);


        $revmodinfo = array_slice($allsections, 0, course_get_format($course)->get_visible_section_count() + 1);
        //   $revmodinfo = array_reverse($requiredsections); //reverse sections
        //  array_unshift($revmodinfo, array_pop($revmodinfo)); //move section 0 back to top
        $started = $ended = false;
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);

        foreach ($revmodinfo as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    //Deprecated function
                    //print_section($course, $thissection, null, null, true, "100%", false, 0);

                    echo $courserenderer->course_section_cm_list($course, $thissection, 0,
                        array('hidecompletion' => false));

                    if ($PAGE->user_is_editing()) {
                        //print_section_add_menus($course, 0, null, false, false, 0);
                        echo $output = $courserenderer->course_section_add_cm_control($course, $thissection, 0,
                            array('inblock' => false));
                    }
                    echo $this->section_footer();
                }
                continue;
            }
            $thissectiondates = course_get_format($course)->get_section_dates($thissection);
            $thefuture = $thissectiondates->start > time();

            if ($thefuture && !$started) {
                if ($canviewhidden) {
                    echo '<fieldset id="futureweeks"><legend>' . get_string('futureweeks', 'format_dateenrolled') . '</legend>';
                    $started = true;
                }
            }
            //if ($section > $course->numsections) {
            if ($section > course_get_format($course)->get_visible_section_count() + 1) {

                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but showavailability is turned on (and there is some available info text).
            $showsection = $thissection->uservisible ||
                ($thissection->visible && !$thissection->available && $thissection->showavailability
                    && !empty($thissection->availableinfo));
            if (!$showsection) {
                // Hidden section message is overridden by 'unavailable' control
                // (showavailability option).
                if (!$course->hiddensections && $thissection->available) {
                    echo $this->section_hidden($section);
                }

                continue;
            }

            if (!$PAGE->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                if ($canviewhidden || !$thefuture) {
                    // Display section summary only.
                    echo $this->section_summary($thissection, $course, null);
                }
            } else {
                if (($canviewhidden && $started && !$ended || $ended)) {
                    echo $this->section_header($thissection, $course, false, 0);
                    if ($thissection->uservisible) {
                        // print_section($course, $thissection, null, null, true, "100%", false, 0);
                        echo $courserenderer->course_section_cm_list($course, $thissection, 0,
                            array('hidecompletion' => false));

                        if ($PAGE->user_is_editing()) {
                            echo $output = $courserenderer->course_section_add_cm_control($course, $thissection, 0,
                                array('inblock' => false));
                        }
                    }
                    echo $this->section_footer();
                }
            }
            if ($thissectiondates->start < (time() + (7 * 24 * 60 * 60)) && !$ended) {
                if ($canviewhidden) {
                    echo '</fieldset>';

                }
                $ended = true;
            }
        }
        if (!$ended) {
            if ($canviewhidden) {
                echo '</fieldset>';

            }
            $ended = true;
        }
        echo $this->end_section_list();
    }

    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections The course_sections entries from the DB
     * @param array $mods used for print_section()
     * @param array $modnames used for print_section()
     * @param array $modnamesused used for print_section()
     * @param int $displaysection The section number in the course which is being displayed
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection)
    {
        global $PAGE;
        $courserenderer = $PAGE->get_renderer('core', 'course');
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        // Can we view the section in question?
        $context = context_course::instance($course->id);
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);
        $thissectiondates = course_get_format($course)->get_section_dates($displaysection);
        if ($thissectiondates->start > time()) {
            $thefuture = true;
        } else {
            $thefuture = false;
        }

        // Can we view the section in question?
        if (!($sectioninfo = $modinfo->get_section_info($displaysection))) {
            // This section doesn't exist
            print_error('unknowncoursesection', 'error', null, $course->fullname);
            return;
        }

        if (!$sectioninfo->uservisible) {
            if (!$course->hiddensections) {
                echo $this->start_section_list();
                echo $this->section_hidden($displaysection);
                echo $this->end_section_list();
            }
            // Can't view this section.
            return;
        }

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, $displaysection);

        // General section if non-empty.
        $thissection = $modinfo->get_section_info(0);
        if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
            echo $this->start_section_list();
            echo $this->section_header($thissection, $course, true, $displaysection);
            //print_section($course, $thissection, $mods, $modnamesused, true, "100%", false, $displaysection);
            echo $courserenderer->course_section_cm_list($course, $thissection, $displaysection,
                array('hidecompletion' => false));
            if ($PAGE->user_is_editing()) {
                // print_section_add_menus($course, 0, $modnames, false, false, $displaysection);
                echo $output = $courserenderer->course_section_add_cm_control($course, 0, $displaysection,
                    array('inblock' => false));
            }
            echo $this->section_footer();
            echo $this->end_section_list();
        }


        if ($thefuture) {
            if ($canviewhidden) {
                echo '<fieldset id="futureweeks"><legend>' . get_string('futureweek', 'format_dateenrolled') . '</legend>';
            }
        }
        // Start single-section div
        echo html_writer::start_tag('div', array('class' => 'single-section'));

        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $sections, $displaysection);
        $sectiontitle = '';
        $sectiontitle .= html_writer::start_tag('div', array('class' => 'section-navigation header headingblock'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        // Title attributes
        $titleattr = 'mdl-align title';
        if (!$sections[$displaysection]->visible) {
            $titleattr .= ' dimmed_text';
        }
        $sectiontitle .= html_writer::tag('div', get_section_name($course, $sections[$displaysection]), array('class' => $titleattr));
        $sectiontitle .= html_writer::end_tag('div');
        echo $sectiontitle;

        // Now the list of sections..
        echo $this->start_section_list();

        // The requested section page.
        $thissection = $sections[$displaysection];
        echo $this->section_header($thissection, $course, true, $displaysection);
        // Show completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();

        // print_section($course, $thissection, $mods, $modnamesused, true, '100%', false, $displaysection);
        // if ($PAGE->user_is_editing()) {
        //     print_section_add_menus($course, $displaysection, $modnames, false, false, $displaysection);
        //}
        echo $courserenderer->course_section_cm_list($course, $thissection, $displaysection,
            array('hidecompletion' => false));
        if ($PAGE->user_is_editing()) {
            echo $output = $courserenderer->course_section_add_cm_control($course, $displaysection, $displaysection,
                array('inblock' => false));
        }

        echo $this->section_footer();
        echo $this->end_section_list();

        // Display section bottom navigation.
        $courselink = html_writer::link(course_get_url($course), get_string('returntomaincoursepage'));
        $sectionbottomnav = '';
        $sectionbottomnav .= html_writer::start_tag('div', array('class' => 'section-navigation mdl-bottom'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        $sectionbottomnav .= html_writer::tag('div', $courselink, array('class' => 'mdl-align'));
        $sectionbottomnav .= html_writer::end_tag('div');
        echo $sectionbottomnav;

        // close single-section div.
        echo html_writer::end_tag('div');
        if ($thefuture) {
            if ($canviewhidden) {
                echo '</fieldset>';
            }
        }
    }

    /**
     * Generate next/previous section links for naviation
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections The course_sections entries from the DB
     * @param int $sectionno The section number in the coruse which is being dsiplayed
     * @return array associative array with previous and next section link
     */
    protected function get_nav_links($course, $sections, $sectionno)
    {
        // FIXME: This is really evil and should by using the navigation API.
        $canviewhidden = has_capability('moodle/course:viewhiddensections', context_course::instance($course->id))
        or !$course->hiddensections;

        $links = array('previous' => '', 'next' => '');
        $back = $sectionno - 1;
        while ($back > 0 and empty($links['previous'])) {
            if ($canviewhidden || $sections[$back]->uservisible) {
                $params = array();
                if (!$sections[$back]->visible) {
                    $params = array('class' => 'dimmed_text');
                }
                $previouslink = html_writer::tag('span', $this->output->larrow(), array('class' => 'larrow'));
                $previouslink .= get_section_name($course, $sections[$back]);
                $links['previous'] = html_writer::link(course_get_url($course, $back), $previouslink, $params);
            }
            $back--;
        }

        $forward = $sectionno + 1;
        while ($forward <= (course_get_format($course)->get_visible_section_count() + 1) and empty($links['next'])) {
            $nextsectiondates = course_get_format($course)->get_section_dates($sections[$forward]);
            $shownext = $nextsectiondates->start < time();
            if ($shownext || $canviewhidden) {
                if ($canviewhidden || $sections[$forward]->uservisible) {
                    $params = array();
                    if (!$sections[$forward]->visible) {
                        $params = array('class' => 'dimmed_text');
                    }
                    $nextlink = get_section_name($course, $sections[$forward]);
                    $nextlink .= html_writer::tag('span', $this->output->rarrow(), array('class' => 'rarrow'));
                    $links['next'] = html_writer::link(course_get_url($course, $forward), $nextlink, $params);
                }
            }
            $forward++;
        }
        return $links;
    }
}
