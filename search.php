<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/imageclass.php');

require_once($CFG->libdir . '/filelib.php');

$id = required_param('id', PARAM_INT);
$g = optional_param('gallery', '0', PARAM_INT);
$search = optional_param('search', '', PARAM_CLEAN);

$cm      = get_coursemodule_from_id('lightboxgallery', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$gallery = $DB->get_record('lightboxgallery', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

if ($gallery->ispublic) {
    course_setup($course->id);
    $userid = (isloggedin() ? $USER->id : 0);
} else {
    require_login($course, true, $cm);
    $userid = $USER->id;
}

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

add_to_log($course->id, 'lightboxgallery', 'search', 'search.php?id='.$id.'&gallery='.$g.'&search='.$search, $search, 0, $userid);

$PAGE->set_url('/mod/lightboxgallery/search.php', array('id' => $cm->id, 'search' => $search));
$PAGE->set_title($gallery->name);
$PAGE->set_heading($course->shortname);
$PAGE->requires->css('/mod/lightboxgallery/assets/skins/sam/gallery-lightbox-skin.css');
$PAGE->requires->js('/mod/lightboxgallery/gallery-lightbox-min.js');
$PAGE->requires->js('/mod/lightboxgallery/module.js');

echo $OUTPUT->header();

if ($instances = get_all_instances_in_course('lightboxgallery', $course)) {
    $options = array(0 => get_string('all'));
    foreach ($instances as $instance) {
        $options[$instance->id] = $instance->name;
    }

    echo('<form action="search.php">');

    $table = new html_table;
    $table->width = '*';
    $table->align = array('left', 'left', 'left', 'left');
    $table->data[] = array(get_string('modulenameshort', 'lightboxgallery'), html_writer::select($options, 'gallery', $g),
                           '<input type="text" name="search" size="10" value="'.s($search, true).'" />' .
                           '<input type="hidden" name="id" value="'.$cm->id.'" />',
                           '<input type="submit" value="'.get_string('search').'" />') ;
    echo html_writer::table($table);

    echo('</form>');
}

$fs = get_file_storage();

if ($results = $DB->get_records_select('lightboxgallery_image_meta', $DB->sql_like('description', '?', false).($g > 0 ? 'AND gallery = ?' : ''), array('%'.$search.'%', ($g > 0 ? $g : null)))) {
    echo $OUTPUT->box_start('generalbox lightbox-gallery clearfix');

    $hashes = array();

    foreach ($results as $result) {
        if (!isset($hashes[$result->image])) {
            $stored_file = $fs->get_file_by_hash($result->image);

            $image = new lightboxgallery_image($stored_file, $gallery, $cm);
            echo $image->get_image_display_html();
            $hashes[$result->image] = 1;
        }
    }

    echo $OUTPUT->box_end();

} else {

    echo $OUTPUT->box(get_string('errornosearchresults', 'lightboxgallery'));

}

echo $OUTPUT->footer();