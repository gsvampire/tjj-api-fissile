<?php
$errorData = [
    'result' => 0,
    'message' => (string) '您的网络不可用，请稍后再试！',
];
header('Content-Type:application/json; charset=utf-8');
echo json_encode($errorData);
