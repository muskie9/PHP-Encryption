<?php
define('TMPDIR', DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'EncryptedData' . DIRECTORY_SEPARATOR);
if (!file_exists(TMPDIR)) {
	mkdir(TMPDIR, 0777, true);
}

define('CONFIGPATH', __DIR__ . DIRECTORY_SEPARATOR . 'config.php');
@unlink(CONFIGPATH);
file_put_contents(CONFIGPATH, '<?php return array();');

require_once '../encrypteddata.php';

WePay\EncryptedData::setup(array(
	'algorithm'  => function() { return hash('sha256', $_SERVER['REQUEST_TIME'], true); },
	'configPath' => CONFIGPATH,
	'filePath'   => TMPDIR,
));

register_shutdown_function(function() {
	unlink(CONFIGPATH);
	// unlink(TMPDIR . '*');
	// rmdir(TMPDIR);
});
