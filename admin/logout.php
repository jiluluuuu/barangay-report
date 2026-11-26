<?php
require_once __DIR__ . '/../config/server.php';
$_SESSION = []; session_destroy();
header('Location: login.php');
