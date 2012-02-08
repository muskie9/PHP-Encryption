<?php

namespace WePay;
use Exception;

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
