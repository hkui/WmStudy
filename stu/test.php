<?php
\var_dump(\class_exists('\\EventBase',false));
\var_dump(\class_exists('\\\\EventBase',false));


var_dump(class_exists('Event'));

if (\class_exists('\\\\EventBase', false)) {
    $class_name = '\\\\EventBase';
} else {
    $class_name = '\EventBase';
}
var_dump($class_name);
