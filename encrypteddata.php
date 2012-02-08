<?php

namespace WePay;
use Exception;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'encryption.php';

class EncryptedData {

	private static $isSetUp = false; // Has setup been run?
	private static $configs;         // Data about files
	private static $configPath;      // Path to config file
	private static $algorithm;       // Closure that generates the secret key
	private static $filePath;        // Directory in which encrypted data files are stored

	private $file;    // File on which we're operating
	private $version; // Version of file

	/**
	 * Library setup to override defaults
	 * Does not need to be called if using default paths
	 * Absolute paths are highly recommended
	 * @param string $configPath    Path to file which returns configuration array
	 * @param string $algorithmPath Path to file which returns keygen closure
	 * @param string $filePath      Path to directory in which encrypted files are stored
	 */
	public static function setup($configPath = null, $algorithmPath = null, $filePath = null) {
		if (self::$isSetUp) {
			return;
		}
		if (!$configPath) {
			self::$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'files.php';
		}
		if (!$algorithmPath) {
			$algorithmPath = __DIR__ . DIRECTORY_SEPARATOR . 'algorithm.php';
		}
		if (!$filePath) {
			$filePath = __DIR__ . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR;
		}

		self::$algorithm = include $algorithmPath;
		self::$configs   = include self::$configPath;
		self::$filePath  = $filePath;
		self::$isSetUp   = true;
	}

	/**
	 * Wrapper object for reading/writing versioned, encrypted files
	 * @param string $file     Name of versioned file
	 * @param int    $version  Version number of file (defaults to active version if none specified, 0 if none active)
	 */
	public function __construct($file, $version = 0) {
		self::setup();
		if (!$version) {
			$version = self::getActiveVersion($file);
		}
		$this->file = $file;
		$this->version = strtolower($version);
	}

	/**
	 * Write current version to "active" version in config
	 * @return boolean
	 */
	public function activate() {
		self::$configs[$this->file]['active'] = $this->version;
		return self::writeConfigs();
	}

	/**
	 * Decrypt contents of file with key generated using instance details
	 * @return mixed      Decrypted data on success
	 * @throws \Exception On decryption failure
	 */
	public function getData() {
		if (!$this->version) {
			throw new Exception('No version specified');
		}
		$infile = self::getPathForVersion($this->file, $this->version);
		$data = file_get_contents($infile);
		$key  = self::buildEncryptionKey($this->file, $this->version);
		$decrypted = Encryption::decrypt($data, $key);
		if ($parsed = @unserialize($decrypted)) {
			return $parsed;
		}
		throw new Exception('Could not decode data');
	}

	/**
	 * Determine the next version number of the configs
	 * @return int
	 */
	private function getNextVersion() {
		if (isset(self::$configs[$this->file]['versions'])) {
			return max(array_keys(self::$configs[$this->file]['versions'])) + 1;
		}
		else {
			return 1;
		}
	}

	/**
	 * Set up next version of encrypted file
	 * @param  string $author Name of person creating next version
	 * @return int            Next version number on success
	 * @return false          On error
	 */
	public function prepNextVersion($author) {
		if (!$author) {
			throw new Exception("File Author is required");
		}
		$nextVersion = $this->getNextVersion();
		self::$configs[$this->file]['versions'][$nextVersion] = array(
			'author' => $author,
			'update' => date('c')
		);
		$this->version = $nextVersion;
		return self::writeConfigs() ?  $nextVersion : false;
	}

	/**
	 * @param  string     $author Name of person performing rotation
	 * @return boolean
	 * @throws \Exception
	 */
	public function rotate($author) {
		$data = $this->getData();
		$this->prepNextVersion($author);
		return $this->write($data);
	}

	/**
	 * Write out data to standard location at current version
	 * @param  mixed    $data Data to be encrypted (must be un/serializable)
	 * @return boolean
	 */
	public function write($data) {
		$key = self::buildEncryptionKey($this->file, $this->version);
		$outfile = self::getPathForVersion($this->file, $this->version);
		$encrypted = Encryption::encrypt(serialize($data), $key);
		if (false === file_put_contents($outfile, $encrypted)) {
			return false;
		}
		try {
			$testData = $this->read($outfile);
			if ($testData === $data) {
				return true;
			}
			// bad data will be deleted below
		}
		catch (Exception $e) {} // Silently ignore write errors for now
		unlink($outfile);
		return false;
	}

	/**
	 * @param  string $file    File name
	 * @param  int    $version File version
	 * @return string          Encryption key
	 */
	private static function buildEncryptionKey($file, $version) {
		return call_user_func(self::$algorithm, self::$configs[$file]['versions'][$version], $file, $version);
	}

	/**
	 * @param  string $file Name of file
	 * @return int          Active version number (0 if no active version)
	 */
	private static function getActiveVersion($file) {
		if (isset(self::$configs[$file]['active'])) {
			return self::$configs[$file]['active'];
		}
		return 0;
	}

	/**
	 * @param  string $file    File name
	 * @param  int    $version File version
	 * @return string          Path where encrypted file should be located
	 */
	private static function getPathForVersion($file, $version) {
		return self::$filePath . "$file.$version.php";
	}

	/**
	 * @return boolean
	 */
	private static function writeConfigs() {
		// do not call setup or you will overwrite your local changes!
		return (bool) file_put_contents(self::$configPath, '<?php return ' . var_export(self::$configs, true) . ';');
	}

}

