<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

cliente_logout();
redirect('index.php');
