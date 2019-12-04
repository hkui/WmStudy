<?php

$local_socket="tcp://0.0.0.0:8081";
$flags=STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
$context_option=[];
$context_option['socket']['backlog'] = 5;

$context = \stream_context_create($context_option);

//7.1.0加上了tcp_nodelay选项，我这是7.3.4
//stream_context_set_option($context, 'socket', 'tcp_nodelay', true);


$mainSocket = \stream_socket_server($local_socket, $errno, $errmsg, $flags, $context);

if (!$mainSocket) throw new Exception($errmsg);



$socket = \socket_import_stream($mainSocket);

var_dump(socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1));
var_dump(socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1));
var_dump(socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1));






echo "tcp_nodelay:".print_r(socket_get_option($socket,SOL_TCP,TCP_NODELAY),true).PHP_EOL;

print_r(stream_context_get_options($mainSocket));
print_r(stream_context_get_options(socket_export_stream($socket)));


echo PHP_EOL;




