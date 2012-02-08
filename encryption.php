<?php

namespace WePay;
use Exception;

const Cipher  = MCRYPT_RIJNDAEL_128;
const Mode    = MCRYPT_MODE_ECB; // best for encrypting other keys!
const Entropy = MCRYPT_DEV_URANDOM;
const IvLen   = 16; // save result of mcrypt_get_iv_size(Cipher,Mode)

function decrypt($data, $key) {
	$raw = base64_decode($data);
	$iv = substr($raw, 0, IvLen);
	$encrypted = substr($raw, IvLen);
	$decrypted = mcrypt_decrypt(Cipher, $key, $encrypted, Mode, $iv);
	$decrypted = rtrim($decrypted, "\0"); // trim right-padded null bytes
	return $decrypted;
}

function encrypt($plaintext, $key) {
	if (!$iv = mcrypt_create_iv(IvLen, Entropy)) {
		// @codeCoverageIgnoreStart No practical way to simulate IV setup failure
		throw new Exception('Could not create initialization vector');
		// @codeCoverageIgnoreEnd
	}
	$encrypted = mcrypt_encrypt(Cipher, $key, $plaintext, Mode, $iv);
	return base64_encode($iv.$encrypted);
}
