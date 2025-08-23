<?php
echo "get_current_user(): " . get_current_user() . "<br>";
echo "posix_getuid(): " . posix_getuid() . "<br>";
echo "whoami (exec): " . exec('whoami') . "<br>";
?>
