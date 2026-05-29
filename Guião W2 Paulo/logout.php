<?php
require_once 'sessao.php';
session_destroy();
header('Location: ../index.html');
exit;
