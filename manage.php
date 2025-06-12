<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$systemcontext = context_system::instance();
require_capability('block/coursefeedback:manageevaluators', $systemcontext);

$contextid = optional_param('contextid', 0, PARAM_INT);
$username  = optional_param('username', '', PARAM_RAW_TRIMMED);
$action    = optional_param('action', '', PARAM_ALPHA);
$raid      = optional_param('raid', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/blocks/coursefeedback/manage.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('manageevaluators', 'block_coursefeedback'));
$PAGE->set_heading(get_string('manageevaluators', 'block_coursefeedback'));

$role = $DB->get_record('role', ['shortname' => 'evaluationsbeauftragter']);
if (!$role) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('evalrolemissing', 'block_coursefeedback'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'assign' && confirm_sesskey()) {
    if ($contextid && $username) {
        if ($user = $DB->get_record('user', ['username' => $username, 'deleted' => 0])) {
            role_assign($role->id, $user->id, $contextid);
            redirect($PAGE->url);
        }
    }
} else if ($action === 'unassign' && confirm_sesskey()) {
    if ($assignment = $DB->get_record('role_assignments', ['id' => $raid, 'roleid' => $role->id])) {
        role_unassign($role->id, $assignment->userid, $assignment->contextid, '', $raid);
        redirect($PAGE->url);
    }
}

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('summary', 'block_coursefeedback'));
$table = new html_table();
$table->head = [
    get_string('username', 'block_coursefeedback'),
    get_string('context', 'block_coursefeedback'),
    get_string('remove', 'block_coursefeedback'),
];
$assignments = $DB->get_records('role_assignments', ['roleid' => $role->id]);
foreach ($assignments as $assign) {
    $user = $DB->get_record('user', ['id' => $assign->userid]);
    $context = context::instance_by_id($assign->contextid);
    $removeurl = new moodle_url('/blocks/coursefeedback/manage.php', [
        'action' => 'unassign',
        'raid' => $assign->id,
        'sesskey' => sesskey(),
    ]);
    $table->data[] = [
        fullname($user),
        $context->get_context_name(false, true),
        html_writer::link($removeurl, get_string('remove', 'block_coursefeedback')),
    ];
}
if (!empty($table->data)) {
    echo html_writer::table($table);
} else {
    echo get_string('none');
}

echo html_writer::tag('h2', get_string('assignrole', 'block_coursefeedback'));

$categories = core_course_category::get(0)->get_children();
$courses = get_courses();
$options = [];
foreach ($categories as $cat) {
    $c = context_coursecat::instance($cat->id);
    $options[$c->id] = get_string('category') . ': ' . $cat->get_formatted_name();
}
foreach ($courses as $course) {
    $c = context_course::instance($course->id);
    $options[$c->id] = get_string('course') . ': ' . format_string($course->fullname);
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'assign']);
    echo html_writer::label(get_string('context', 'block_coursefeedback'), 'contextid');
    echo html_writer::select($options, 'contextid', $contextid, ['' => get_string('choosedots')] );
    echo html_writer::empty_tag('br');
    echo html_writer::label(get_string('username', 'block_coursefeedback'), 'username');
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'username', 'id' => 'username']);
    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('assign', 'block_coursefeedback')]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
