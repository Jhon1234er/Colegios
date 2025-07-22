<?php
session_start();
session_destroy();
header("Location: /COLEGIOS/public/index.php");
exit;
