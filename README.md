Versioned data encryption system
================================
Store arbitrary data in versioned, encrypted files. This was built for sensitive config files (e.g., production db credentials), but should work fine with any type of data that can be serialized/unserialized/var_exported by PHP.

Very, very important
--------------------
Encryption is a powerful tool. It is incredibly effective when used correctly, and tragically dangerous when used incorrectly. I recommend reading every line of code in the library, and do not implement it unless you understand what is happening. Have someone with security experience review your integration and algorithm.

If you are thinking about using this for user password storage, **stop**. At the time of writing (Feb 2012), the *only* appropriate way to store passwords is with bcrypt, which is - by design - slow and not reversible. *No form of encryption is suitable for user passwords; they must only be stored after being put through a one-way hashing algorithm.*

Requirements
------------
* PHP 5.3 or later
* mcrypt extension

Setup
-----
### files/
Create a files directory or symlink in this base-level directory. This is where the encrypted data will be stored. 

This path can be overwritten, see "Usage - Library Initialization" below.

### files.php
This file stores version history and the active version ID. There should be no need to edit this manually - the CLI script takes care of everything. Just rename `files.php.default` to `files.php`.

This path can be overwritten, see "Usage - Library Initialization" below.

### algorithm.php
Create algorithm.php in this base-level directory. It must return a closure that accepts three parameters and returns the 256-bit decryption key as a string.

This path can be overwritten, see "Usage - Library Initialization" below.

It should look like this:

	<?php
	return function (array $config, $file, $version) {
		// Generate $encryptionKey in some sort of deterministic way
		return $encryptionKey;
	};

`$config` is an array containing two keys: `'author'` and `'update'` (ISO8601 date string)
`$file` is the name of the config file being loaded  
`$version` is the version number  

This could implement some sort of hashing mechanism, talk to a crypto system, or something else. The same input must always return the same value, so avoid using system time or RNGs unless part of an external crypto system.

**Important**: the return value must be a 256-bit (32-byte) string. *For speed, there is no runtime checking of the return value. Invalid return values may produce unpredictable results, including insecure data storage.*  Raw output of SHA256 (`hash('sha256', $data, true)`) is a perfect match.

**Also important**: If you change the algorithm, you will most likely break your existing configs. Any alteration must maintain backwards compatibility, at least until all files have been version-bumped with the new algorithm.

Usage
-----
### Library Initialization
If using the default filepaths as described in "setup" above, there is no need to initialize the library.  To change defaults, run the following:

	<?php
	\WePay\EncryptedData::setup(array(
		'algorithm'     => callback, // anything callable by call_user_func()
		'algorithmPath' => '/path/to/algorithm.php',
		'configPath'    => '/path/to/files.php',
		'filePath'      => '/path/to/files/directory/', // needs trailing slash!
	));
	// If both algorithm and algorithmPath are provided, algorithm will take precedence

### API

#### \WePay\EncryptedData::setup(array $options)
Initialize the library, overriding configuration defaults as specified. Calling this is not necessary if using default paths.

`$options` is an associative array of options to override the default values. See Library Initialization, above.

returns `void`

#### \WePay\EncryptedData::prepInitialVersion($fileName, $data, $author)
Wrapper to perform the initial data encryption.

`$fileName` is the key/name under which the data will be stored.  
`$data` is the data to be stored encrypted.  
`$author` is the name of the person making changes.

returns `\WePay\EncryptedData` object

#### new \WePay\EncryptedData($file, $version = 0)
Constructor

`$file` is the key/name under which the data will be accessed  
`$version` is the optional version number to access. If one is not specified, the active version will be used if available.

returns `void`

#### \WePay\EncryptedData->activate()
Make the current version active

returns `boolean`

#### \WePay\EncryptedData->getData()
Fetch and decrypt the original data

returns `mixed` 
throws `\Exception` on failure

#### \WePay\EncryptedData->getPath()
Fetch the path to the encrypted data file

returns `string`

#### \WePay\EncryptedData->getVersion()
Fetch the current version number

returns `integer` 

#### \WePay\EncryptedData->prepNextVersion($author)
Prepare the next version of the file, and return the new version number. *This changes the version number of this object instance.*

`$author` is the name of the person making changes.

returns `integer`  
returns `false` on failure

#### \WePay\EncryptedData->rotate($author)
Rotate to the next available key. This is the same as calling `getData()`, `prepNextVersion($author)` and `write($data)` in that order.

`$author` is the name of the person making changes.

returns `boolean`  
throws `\Exception` on failure

#### \WePay\EncryptedData->write($data)
Store data, encrypted. This *does not* automatically increment the version; call `prepNextVersion($author)` to do so.

`$data` (any serializable datatype) is the data to store.

returns `boolean`

Examples
--------
### Loading Encrypted Data
This is probably all you will need to use in your day-to-day code, initial encryption and rotation can be done with the CLI script.

	// $name is the filename specified during encryption
	try {
		$ed = new \WePay\EncryptedData($name); // This will use the "active" version unless you specify a version number as the second argument
		return $ed->getData();
	}
	catch (Exception $e) {
		error_log("Loading encrypted data $name failed: " . $e->getMessage() . $e->getTraceAsString());
		return FALSE; // adjust failure return value to taste
	}

	
### CLI
Run pcrypt (`./pcrypt`) for usage information.

Notes
-----
Our QSA has confirmed that dynamic key generation by a secret algorithm is sufficient for Level 1 PCI Compliance since the decryption key is never written to disk. A system requiring some sort of human activity (e.g. multiple people enter passwords to decrypt the master key into memory) is preferable and more secure, but may be impractical at smaller scale. Your mileage with QSA approval of dynamic key generation (should your algorithm implement it) may vary.