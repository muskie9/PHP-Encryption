<?php

class Encryption {
	const Cipher  = MCRYPT_RIJNDAEL_128;
	const Mode    = MCRYPT_MODE_ECB; // best for encrypting other keys!
	const Entropy = MCRYPT_DEV_URANDOM;
	const IvLen   = 16; // save result of mcrypt_get_iv_size(Cipher,Mode)

	public static function decrypt($data, $key) {
		$raw = base64_decode($data);
		$iv = substr($raw, 0, self::IvLen);
		$encrypted = substr($raw, self::IvLen);
		$decrypted = mcrypt_decrypt(self::Cipher, $key, $encrypted, self::Mode, $iv);
		$decrypted = rtrim($decrypted, "\0"); // trim right-padded null bytes
		return $decrypted;
	}

	public static function encrypt($plaintext, $key) {
		if (!$iv = mcrypt_create_iv(self::IvLen, self::Entropy)) {
			throw new Exception('Could not create initialization vector');
		}
		$encrypted = mcrypt_encrypt(self::Cipher, $key, $plaintext, self::Mode, $iv);
		return base64_encode($iv.$encrypted);
	}
}

class EncryptedData {
	const Suffix = '.plain.php';
	const ConfigPath = 'files.php';
	const SavePath   = 'files';

	private static $configs = null;
	private static $algorithm;
	private static function setup() {
		if (self::$configs !== null) {
			return;
		}
		self::$configs   = include __DIR__ . DIRECTORY_SEPARATOR . self::ConfigPath;
		self::$algorithm = include __DIR__ . DIRECTORY_SEPARATOR . 'algorithm.php';
	}
	private static function buildEncryptionKey($file, $version) {
		return call_user_func(self::$algorithm, self::$configs[$file]['versions'][$version], $file, $version);
	}

	/**
	 * Get the decrypted contents of the file
	 * Uses "active" version if none specified
	 * @param  string    $file
	 * @param  int       &$version = null (if null, set to active version by reference)
	 * @return mixed
	 * @throws Exception
	 */
	public static function load($file, &$version = null) {
		self::setup();
		if ($version === null) {
			if (!isset(self::$configs[$file]['active'])) {
				throw new Exception("No active encryption key for $file");
			}
			$version = self::$configs[$file]['active'];
		}
		$infile = self::getPathForVersion($file, $version);
		if (!file_exists($infile)) {
			throw new Exception("Config file not found ($file:$version)");
		}
		$data = file_get_contents($infile);
		$key = self::buildEncryptionKey($file, $version);
		$decrypted = Encryption::decrypt($data, $key);
		if ($parsed = @unserialize($decrypted)) {
			return $parsed;
		}
		throw new Exception("Could not decrypt and/or load $file config");

	}
	/**
	 * Decrypt version and write to disk ($file.$version.plain.php) - used for editing files
	 * @see self::load()
	 * @return string Path on success
	 * @return false  on failure
	 */
	static function decrypt($file, $version = null) {
		$data = self::load($file, $version);
		$outfile = self::getPlaintextPathForVersion($file, $version);
		if (file_put_contents($outfile, '<?php return ' . var_export($data, true) . ';') !== false) {
			return $outfile;
		}
		return false;
		
	}
	/**
	 * Encrypt plaintext and write to disk - used for saving edited files
	 * Deletes plaintext if written and checked successfully
	 * @param  string $file
	 * @param  int    $version
	 * @return boolean
	 * @throws Exception
	 */
	static function encrypt($file, $version) {
		self::setup();
		$infile = self::getPlaintextPathForVersion($file, $version);
		if (!file_exists($infile)) {
			throw new Exception('Could not load raw config');
		}

		$key = self::buildEncryptionKey($file, $version);
		// Load plaintext config
		$raw = require $infile;
		$data = serialize($raw);
		$encrypted = Encryption::encrypt($data, $key);

		$outfile = self::getPathForVersion($file, $version);
		if (file_put_contents($outfile, $encrypted) === false) {
			return false;
		}
		if (self::load($file,$version) === $raw) {
			unlink($infile);
			return true;
		}
		else {
			unlink($outfile);
			return false;
		}

	}

	private static function writeConfigs() {
		// do not call setup or you will overwrite your local changes!
		return (bool) file_put_contents(self::ConfigPath, '<?php return ' . var_export(self::$configs, true) . ';');
	}

	private static function getPathForVersion($file, $version) {
		return __DIR__ . DIRECTORY_SEPARATOR . self::SavePath . DIRECTORY_SEPARATOR . basename($file) . ".$version.php";
	}

	public static function getPlaintextPathForVersion($file, $version) {
		return __DIR__ . DIRECTORY_SEPARATOR . self::SavePath . DIRECTORY_SEPARATOR . basename($file) . ".$version" . self::Suffix;
	}

	public static function getNextVersion($file) {
		self::setup();
		if (!isset(self::$configs[$file])) {
			return 1;
		}
		else {
			return max(array_keys(self::$configs[$file]['versions'])) + 1;
		}
		
	}

	public static function prepNextVersion($file, $author) {
		if (!$author) {
			throw new BadMethodCallException("File Author is required");
		}
		self::setup();
		$file = basename($file);
		$nextVersion = self::getNextVersion($file);
		self::$configs[$file]['versions'][$nextVersion] = array(
			'author' => $author,
			'update' => date('c')
		);
		return self::writeConfigs() ?  $nextVersion : false;
	}

	public static function setActiveVersion($file, $version) {
		$file = basename($file);
		self::$configs[$file]['active'] = $version;
		return self::writeConfigs();
	} // function setActiveVersion

	public static function rotate($file, $author, $bump, $oldVersion) {
		$newVersion = self::prepNextVersion($file, $author);
		// Fixme: this should not need to write decrypted data to disk to bump version
		$oldPath = self::decrypt($file, $oldVersion);
		$newPath = self::getPlaintextPathForVersion($file, $newVersion);
		if (!rename($oldPath, $newPath)) {
			unlink($oldPath);
			unlink($newPath);
			return false;
		}
		if (!self::encrypt($file, $newVersion)) {
			return false;
		}
		if ($bump) {
			EncryptedData::setActiveVersion($file, $newVersion);
		}
		return $newVersion;
	}

}
