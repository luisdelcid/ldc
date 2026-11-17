<?php

namespace LDC\Deps;

if (!\class_exists('LDC\Deps\Parsedown', \false)) {
    require __DIR__ . '/ParsedownModern.php';
}
