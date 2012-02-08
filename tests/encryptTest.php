<?php

class Encrypt_Tests extends PHPUnit_Framework_TestCase {

	public function testFileEncryption() {
		$data = __CLASS__;
		$ed = WePay\EncryptedData::prepInitialVersion(__FUNCTION__, $data, __CLASS__);
		$version = $ed->getVersion();
		$filePath = $ed->getPath();
		$this->assertFileExists($filePath, 'Encrypted file should exist');
		$this->assertNotEquals($data, file_get_contents($filePath), 'Contents of encrypted file must not be the original data');
	}

	public function testPreppingNextVersionWithNoAuthorFails() {
		$data = __CLASS__;
		$ed = WePay\EncryptedData::prepInitialVersion(__FUNCTION__, $data, __CLASS__);
		try {
			$ed->prepNextVersion('');
		}
		catch (Exception $e) {
			return;
		}
		$this->fail('Trying to prep next version with no author specified should have thrown an exception');
	}

}