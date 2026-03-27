<?php declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;

Auth::startSession();
Auth::logout();

header('Location: ' . Auth::loginUrl());
exit;

