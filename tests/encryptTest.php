<?php

class Encrypt_Tests extends PHPUnit_Framework_TestCase {

	public function testFileEncryption() {
		$data = __CLASS__;
		$ed = WePay\EncryptedData::prepInitialVersion(__CLASS__, $data, __CLASS__);
		$version = $ed->getVersion();
		$filePath = $ed->getPath();
		$this->assertFileExists($filePath, 'Encrypted file should exist');
		$this->assertNotEquals($data, file_get_contents($filePath), 'Contents of encrypted file must not be the original data');
	}

}