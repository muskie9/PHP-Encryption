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

### algorithm.php
Create algorithm.php in this base-level directory. It must return a closure that accepts three parameters and returns the 256-bit decryption key as a string.

It should look like this:

	return function (array $config, $file, $version) {
		// Generate $encryptionKey in some sort of deterministic way
		return $encryptionKey;
	};

$config is an array containing two keys: 'author' and 'update' (ISO8601 date string)
$file is the name of the config file being loaded  
$version is the version number  

This could implement some sort of hashing mechanism, talk to a crypto system, or something else. The same input must always return the same value, so avoid using system time or RNGs unless part of an external crypto system.

**Important**: the return value must be a 256-bit (32-byte) string. *For speed, there is no runtime checking of the return value. Invalid return values may produce unpredictable results, including insecure data storage.*  Raw output of SHA256 (`hash('sha256', $data, true)`) is a perfect match.

**Also important**: If you change the algorithm, you will most likely break your existing configs. Any alteration must maintain backwards compatibility, at least until all files have been version-bumped with the new algorithm.

Usage
-----
(coming soon when implementation is finished)

Notes
-----
Our QSA has confirmed that dynamic key generation by a secret algorithm is sufficient for Level 1 PCI Compliance since the decryption key is never written to disk. A system requiring some sort of human activity (e.g. multiple people enter passwords to decrypt the master key into memory) is preferable and more secure, but may be impractical at smaller scale. Your mileage with QSA approval of dynamic key generation (should your algorithm implement it) may vary.