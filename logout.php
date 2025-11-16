<?php
session_start();
session_destroy();
header('Location: /hrms/login.php');
exit();
?>
