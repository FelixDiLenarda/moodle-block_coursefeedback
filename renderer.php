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
 * Renderer
 *
 * @package    block
 * @subpackage coursefeedback
 * @copyright  2023 innoCampus, Technische Universität Berlin
 * @author     2011-2023 onwards Jan Eberhardt
 * @author     2022 onwards Felix Di Lenarda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_coursefeedback_renderer extends plugin_renderer_base {

    /**
     * @return string
     */
    public function render_manage_link() {
        return html_writer::link(new moodle_url("/admin/settings.php?section=blocksettingcoursefeedback"),
            get_string("page_link_settings", "block_coursefeedback"));
    }

    /**
     * @param number $courseid
     * @param number $feedbackid
     * @return string
     */
    public function render_results_link($courseid, $feedbackid) {
        return html_writer::link(new moodle_url("/blocks/coursefeedback/view.php",
            array("course" => $courseid, "feedback" => $feedbackid)),
            get_string("page_link_viewresults", "block_coursefeedback"));
    }

    /**
     * @param number $courseid
     * @return array
     */
    public function render_result_links($answerredfbs) {
        $results = [];
        foreach ($answerredfbs as $feedback) {
            $results[] = html_writer::link(new moodle_url("/blocks/coursefeedback/view.php",
                ["course" => $feedback->course, "feedback" => $feedback->id]), format_string($feedback->name));
        }
        return $results;
    }

    /**
     * @return string
     */
    public function render_ranking_link() {
        return html_writer::link(new moodle_url("/blocks/coursefeedback/ranking.php"),
                get_string("page_link_rankings", "block_coursefeedback"));
    }

    /**
     * @return string
     */
    public function render_moreinfo_link($params) {
        return html_writer::link(new moodle_url("/blocks/coursefeedback/feedbackinfo.php", $params),
            get_string("infopage_link_feedbackinfo", "block_coursefeedback"));
    }

    /**
     * @param object $feedback
     * @param array $openquestions
     * @return string
     */
    public function render_notif_message($feedback, $openquestions) {
        // TODO do we need the export for template function here?
        // Template vars are automatically escaped
        $urlparams = [
            'feedback' => $feedback->id,
            'course' => $this->page->course->id
        ];
        $data = [
            'fbheading' => $feedback->heading,
            'qid' => $openquestions['currentopenqstn']->questionid,
            'qsum' => $openquestions['questionsum'],
            'qtext' => $openquestions['currentopenqstn']->question,
            'link' => new moodle_url("/blocks/coursefeedback/feedbackinfo.php", $urlparams),
            'questiontype' => intval($openquestions['currentopenqstn']->questiontype)

        ];

        $questiontype = intval($openquestions['currentopenqstn']->questiontype);
        return parent::render_from_template('block_coursefeedback/questionnotif', $data);
    }

    /**
     * @param object $feedback
     * @param int $courseid
     * @return string
     */
    public function render_notif_message_teacher($feedback, $courseid) {
        $message = get_string("notif_feedbackactive", "block_coursefeedback");
        if (get_config("block_coursefeedback", "allow_hiding")) {
            $message .= ' ' . get_string("notif_deactivate_howto", "block_coursefeedback");
        }
        $message .= ' ' . $this->render_moreinfo_link(array("feedback" => $feedback->id, "course" => $courseid));
        $message .= ' | ' . $this->render_results_link($courseid, $feedback->id);
        return $message;
    }

    /**
     * Renders the essayquestions
     *
     * @param array $essayquestions - array of question objects $essayquestions
     * @param int $courseid
     * @param int $feedbackid
     * @return string
     */
    public function render_essay_questions($essayquestions, $courseid, $feedbackid) {

        // Provide downloadlink for textanswers for all questions.
        $params = [
            "course" => $courseid,
            "feedback" => $feedbackid,
            "download" => "csv"
        ];
        $essayhtml = html_writer::link(new moodle_url("/blocks/coursefeedback/essayanswers.php", $params),
            get_string("page_link_download", "block_coursefeedback", "CSV"));

        // Create and fill Textanswer table.
        $table = new html_table();
        $table->data = [];

        foreach ($essayquestions as $essayquestion) {
            // Cell 1: "Question <id>:"
            $text = get_string("form_header_question", "block_coursefeedback", $essayquestion->questionid)
                . ": ";

            // Cell 2: "<questiontext>"
            $questiontext = format_string($essayquestion->question);

            //Cell 3 "Show answers" (lnk).
            $params = [ 'course' => $courseid,
                    'feedback' => $essayquestion->coursefeedbackid,
                    'question' => $essayquestion->questionid ];
            $answerlink = html_writer::link(new moodle_url("/blocks/coursefeedback/essayanswers.php", $params),
                get_string("table_html_showanswers", "block_coursefeedback"));

            // Insert in table
            $cells = [ $text, $questiontext, $answerlink ];
            $row = new html_table_row($cells);
            $table->data[] = $row;

        }
        $essayhtml .= html_writer::table($table);
        return $essayhtml; //$html;
    }

    /**
     * Render the essayanswers page
     *
     * @param array $essayquestions - array of question objects $essayquestions
     * @return string
     */
    public function render_essay_answers($courseid, $feedbackid, $questionid, $totalcount, $page=0, $perpage=30) {
        global $DB;

        // Start output with heading "Essay Feedbackresults"
        $answerhtml = html_writer::tag('h3',get_string("questiontype_essay", "block_coursefeedback")
            . ' ' . get_string("resultspage_title", "block_coursefeedback"));

        // Add infos for $totalcount and current $page.
        $totalpages = ceil($totalcount / $perpage);
        $answerhtml .= html_writer::tag('div',
                get_string("page_html_totalanscountinfo", "block_coursefeedback",
                    ['totalcount' => $totalcount, 'page' => $page + 1, 'totalpages' => $totalpages ]));

        // Add download
        $params = [
            "course" => $courseid,
            "feedback" => $feedbackid,
            "question" => $questionid,
            "download" => "csv"
        ];
        $link = html_writer::link(new moodle_url("/blocks/coursefeedback/essayanswers.php", $params),
            get_string("page_link_download", "block_coursefeedback", "CSV"));
        $answerhtml .= html_writer::tag('div', $link);

        // Get all textanswers for the given $questionid and add them in a table.
        $answers = $DB->get_records('block_coursefeedback_textans', ['course' => $courseid,
                'coursefeedbackid' => $feedbackid,
                'questionid' => $questionid], '', 'id,textanswer', $page, $perpage);

        // Get the $question(text) of the given $questionid
        $question = block_coursefeedback_get_questions_by_language($feedbackid, current_language(),
            CFB_QUESTIONTYPE_ESSAY, 'questionid', 'questionid,question', $questionid);
        $questiontext = format_string(reset($question)->question);

        // Create and fill table .
        $table = new html_table();
        $table->head = [get_string("notif_question", "block_coursefeedback").' '.$questionid.': ',
            $questiontext];

        foreach ($answers as $answer) {
            $cell = new html_table_cell(format_string($answer->textanswer));
            $cell->colspan = 2;
            $table->data[] = new html_table_row([$cell]);
        }
        $answerhtml .= html_writer::table($table);

        return $answerhtml;
    }
}
