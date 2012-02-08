<?php

class Rotate_Tests extends PHPUnit_Framework_TestCase {

	public function testFileRotation() {
		$data = __CLASS__;
		$ed = WePay\EncryptedData::prepInitialVersion(__CLASS__, $data, __CLASS__);
		$version = $ed->getVersion();
		$v1Path = $ed->getPath();
		$this->assertFileExists($v1Path, 'File should exist for v1');
		$this->assertTrue($ed->rotate(__CLASS__), 'Rotation should work');
		$this->assertEquals($version + 1, $ed->getVersion(), 'Version should have been bumped');
		$v2Path = $ed->getPath();
		$this->assertFileExists($v2Path, 'File should exist for v2');
		$this->assertNotEquals($v1Path, $v2Path, 'Files for v1 and v2 should be in different locations');
		$this->assertFileNotEquals($v1Path, $v2Path, 'Contents of v1 and v2 should differ');
		$this->assertEquals($data, $ed->getData(), 'Data should not have changed after rotation');
	}

}