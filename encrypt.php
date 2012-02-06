<?php
date_default_timezone_set('America/Los_Angeles');

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

class EncryptedConfig {
	const Suffix = '.plain.php';
	const ConfigPath = './files.php';
	const SavePath   = './configs/';

	private static $configs = null;
	private static $algorithm;
	private static function setup() {
		if (self::$configs !== null) {
			return;
		}
		self::$configs   = include self::ConfigPath;
		self::$algorithm = include './algorithm.php';
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
		$infile = self::SavePath . "$file.$version.php";
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
	 */
	static function decrypt($file, $version = null) {
		$data = self::load($file, $version);
		$outfile = self::SavePath . basename($file) . ".$version" . self::Suffix;
		return (bool) file_put_contents($outfile, '<?php return ' . var_export($data, true) . ';');
		
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
		$infile = self::SavePath . basename($file) . ".$version" . self::Suffix;
		if (!file_exists($infile)) {
			throw new Exception('Could not load raw config');
		}

		$key = self::buildEncryptionKey($file, $version);
		// Load plaintext config
		$raw = require $infile;
		$data = serialize($raw);
		$encrypted = Encryption::encrypt($data, $key);

		$outfile = self::SavePath . basename($file) . ".$version.php";
		if (file_put_contents($outfile, $encrypted) === false) {
			return false;
		}
		if (self::load($file,$version) === $raw) {
			unlink($infile);
			return true;
		}

	}

	private static function writeConfigs() {
		// do not call setup or you will overwrite your local changes!
		return (bool) file_put_contents(self::ConfigPath, '<?php return ' . var_export(self::$configs, true) . ';');
	}

	public static function prepNextVersion($file, $author) {
		self::setup();
		if (!isset(self::$configs[$file])) {
			$maxVersion = 1;
		}
		else {
			$maxVersion = max(array_keys(self::$configs[$file]['versions'])) + 1;
		}
		self::$configs[$file]['versions'][$maxVersion] = array(
			'author' => $author,
			'update' => date('c')
		);
		return self::writeConfigs();
	}

}