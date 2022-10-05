<?php

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');

if($_FILES['file']['name'] == 'err.html') {
  http_response_code(404);

  echo json_encode([
    'status' => 'error',
    'file' => $_FILES['file']['name']
  ]);
}
else
  echo json_encode([
    'status' => 'ok',
    'file' => $_FILES['file']['name']
  ]);
