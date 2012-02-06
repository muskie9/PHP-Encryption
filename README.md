Versioned file encryption system
================================

Store arbitrary data in versioned, encrypted files. This was built for sensitive config files (e.g., production db credentials), but should work fine with any type of data that can be serialized/unserialized/var_exported by PHP.

Requirements
------------
* PHP 5.3 or later
* mcrypt extension

Setup
-----

Create algorithm.php in this base-level directory

It should look like this:

	return function ($config, $file, $version) {
		// Generate $encryptionKey in some sort of deterministic way
		// $config contains details about the current version (update time, author)
		// $file is the name of the config file being loaded
		// $version is the version number

		// This could ignore all of the inputs and talk to a crypto process if that's more appropriate
		return $encryptionKey;
	};

