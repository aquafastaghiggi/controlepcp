<?php
$_GET['action'] = 'detalhe_op';
$_GET['op'] = '201055';

// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include API
chdir('/xampp/htdocs/controlepcp');
require 'api_integrated.php';
?>
