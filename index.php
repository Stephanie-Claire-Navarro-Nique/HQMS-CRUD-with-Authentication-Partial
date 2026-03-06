<?php
require 'auth.php';
requireLogin();
header('Location: patients.php');
exit;
