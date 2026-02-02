<?php
echo "PHP: " . PHP_VERSION . "<br>";
echo "OpenSSL: " . OPENSSL_VERSION_TEXT . "<br>";
echo "Config: " . (get_cfg_var('openssl.conf') ?: 'NO') . "<br>";
