<?php
/* Composer WordPress Autoloader @generated by alleyinteractive/composer-wordpress-autoloader */
$autoload = require_once __DIR__ . '/autoload.php';

$vendorDir = __DIR__;
$baseDir = dirname($vendorDir);

\ComposerWordPressAutoloader\AutoloadFactory::registerFromRules(array(
    'Alley\\WP\\Block_Converter\\' => array($vendorDir . '/alleyinteractive/wp-block-converter/src'),
));

return $autoload;