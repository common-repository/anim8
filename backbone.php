<?php

$prefix = 'Anim8_';
global $anim8_boxes;
$anim8_boxes = array();

$anim8_boxes[] = array(
    'id' => 'anim8',
    'title' => 'Slide Info',
    'pages' => array('anim8slides'),
    'context' => 'normal',
    'priority' => 'high',
    'fields' => array(
       array(
        'name'             => 'Slide Background',
        'id'               => "background-image",
        'type'             => 'plupload_image',
        'max_file_uploads' => 1,
        ),
       ),    
    );

function Anim8_register_meta_boxes() {
    global $anim8_boxes;
    if (class_exists('anim8_Meta_Box')) {
        foreach ($anim8_boxes as $anim8_box) {
            new anim8_Meta_Box($anim8_box);
        }
    }
}
add_action('admin_init', 'Anim8_register_meta_boxes');

?>