<?php

require_once __DIR__ . '/includes/config.php';
header('Location: ' . rtrim(BASE_URL, '/') . '/home', true, 302);
exit;