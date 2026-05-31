<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Category.php';

jsonResponse(true, 'OK', ['categories' => Category::getAll()]);
