<?php
require __DIR__.'/../src/bootstrap.php';
header('Location: '.(mcs_installed()?'/admin.php':'/install.php'));exit;
