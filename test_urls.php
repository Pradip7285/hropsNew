<?php
require_once 'config/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>URL Test</title></head><body>";
echo "<h1>URL Configuration Test</h1>";

echo "<h2>Current Configuration:</h2>";
echo "<p><strong>BASE_URL:</strong> " . BASE_URL . "</p>";

echo "<h2>URL Tests:</h2>";
echo "<ul>";
echo "<li>Direct BASE_URL: <a href='" . BASE_URL . "'>" . BASE_URL . "</a></li>";
echo "<li>BASE_URL + /dashboard.php: <a href='" . BASE_URL . "/dashboard.php'>" . BASE_URL . "/dashboard.php</a></li>";
echo "<li>BASE_URL + /interviews/list.php: <a href='" . BASE_URL . "/interviews/list.php'>" . BASE_URL . "/interviews/list.php</a></li>";
echo "<li>BASE_URL + /interviews/update_status.php: <a href='" . BASE_URL . "/interviews/update_status.php'>" . BASE_URL . "/interviews/update_status.php</a></li>";
echo "</ul>";

echo "<h2>JavaScript URL Test:</h2>";
echo "<script>";
echo "console.log('BASE_URL from PHP: ', '" . BASE_URL . "');";
echo "console.log('Test URL: ', '" . BASE_URL . "/interviews/update_status.php');";
echo "</script>";

echo "<h2>Navigation Test:</h2>";
echo "<button onclick=\"testNavigation()\">Test Interview Update Status URL</button>";

echo "<script>";
echo "function testNavigation() {";
echo "  const testUrl = '" . BASE_URL . "/interviews/update_status.php?id=1&status=test';";
echo "  console.log('Generated URL:', testUrl);";
echo "  alert('Generated URL: ' + testUrl);";
echo "}";
echo "</script>";

echo "</body></html>";
?> 