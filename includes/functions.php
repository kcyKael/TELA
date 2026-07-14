<?php
function cleanInput($value)
{
    return trim($value);
}

function escapeOutput($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirectTo($path)
{
    header('Location: ' . $path);
    exit;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
