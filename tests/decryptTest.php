<?php

class Decrypt_Tests extends PHPUnit_Framework_TestCase {

	private function prep($file, $data) {
		return WePay\EncryptedData::prepInitialVersion($file, $data, __CLASS__);
	}

	public function dataTypes() {
		return array(
			//    data                     gettype(data)
			array(null,                    'NULL'),
			array(true,                    'boolean'),
			array(false,                   'boolean'),
			array('1234',                  'string'),
			array(1234,                    'integer'),
			array(0.1234,                  'double'),
			array(array(1,2,3,4),          'array'),
			array(array(1=>2,3=>4),        'array'),
			array(array('1'=>2,3=>'4'),    'array'),
			array((object) array(1,2,3,4), 'object'),
		);
	}

	/**
	 * @dataProvider dataTypes
	 */
	public function testFileDecryption($data, $type) {
		$file = __FUNCTION__ . $type;
		$ed = $this->prep($file, $data);
		$version = $ed->getVersion();
		unset($ed);

		$new = new WePay\EncryptedData($file, $version);
		$this->assertEquals($data, $new->getData(), 'Data decrypted from new instance should be identical to original');
		$this->assertSame(gettype($data), $type,  'Data decrypted from new instance should be the same type as original');
	}

	public function testDecryptingCorruptedFileFails() {
		$ed = $this->prep(__FUNCTION__, 'data');
		$file = $ed->getPath();
		// Intentionally ruin data
		file_put_contents($file, '');
		try {
			$ed->getData();
		}
		catch (Exception $e) {
			return;
		}
		$this->fail('Reading bogus data did not throw an exception');
	}

	public function testDecryptingMissingFileFails() {
		$ed = $this->prep(__FUNCTION__, 'data');
		$file = $ed->getPath();
		// Intentionally ruin data
		unlink($file);
		try {
			$ed->getData();
		}
		catch (Exception $e) {
			return;
		}
		$this->fail('Reading bogus data did not throw an exception');
	}

}