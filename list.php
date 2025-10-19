<?php
echo "<h2>Directory Index Files</h2>";
$files = glob("*.php");
echo "<ul>";
foreach($files as $file) {
    echo "<li>" . htmlspecialchars($file) . " - " . filesize($file) . " bytes</li>";
}
echo "</ul>";

echo "<h2>Directory Configuration</h2>";
$htaccess = file_exists(".htaccess") ? "Found" : "Not found";
echo "<p>.htaccess: " . $htaccess . "</p>";

if(file_exists(".htaccess")) {
    echo "<pre>" . htmlspecialchars(file_get_contents(".htaccess")) . "</pre>";
}

$possible_index = ["index.php", "index.html", "default.php", "home.php"];
echo "<h2>Possible Index Files</h2><ul>";
foreach($possible_index as $idx) {
    echo "<li>" . $idx . ": " . (file_exists($idx) ? "Exists" : "Not found") . "</li>";
}
echo "</ul>";
?>