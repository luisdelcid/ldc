<?php

namespace LDC\Deps;

require __DIR__ . '/loader.php';
$server = new Wpup_UpdateServer();
$server->handleRequest();
