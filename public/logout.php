<?php
session_start();
session_destroy();
header('Content-Type: application/json');
echo json_encode(['success' => true]); 