<?php

class Activate_Tests extends PHPUnit_Framework_TestCase {

	public function testVersionActivation() {
		$v1data = 'version 1 data';
		$v2data = 'version 2 data';
		$file = __CLASS__;
		$ed = WePay\EncryptedData::prepInitialVersion($file, $v1data, __CLASS__);
		$this->assertEquals(1, $ed->getVersion(), 'Initial version should be 1');

		$default = new WePay\EncryptedData($file);
		$this->assertEquals(0, $default->getVersion(), 'Object with unspecified version should be 0 pre-activation');
		unset($default);

		$ed->activate();

		$default = new WePay\EncryptedData($file);
		$this->assertEquals(1, $default->getVersion(), 'Default object should be at v1');
		$this->assertEquals($v1data, $default->getData(), 'Data should match v1');
		unset($default);

		$ed->prepNextVersion(__CLASS__);
		$ed->write($v2data);

		$default = new WePay\EncryptedData($file);
		$this->assertEquals(1, $default->getVersion(), 'Default object should still be at v1');
		$this->assertEquals($v1data, $default->getData(), 'Data should still match v1');
		unset($default);

		$ed->activate();

		$default = new WePay\EncryptedData($file);
		$this->assertEquals(2, $default->getVersion(), 'Default object should now be at v2');
		$this->assertEquals($v2data, $default->getData(), 'Data should now match v2');
	}

}