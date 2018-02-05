<?php

class PassHash {
	// Blowfish
	private static $algo = '$2a';

	// Cost parameter
	private static $cost = '$10';

	// Mainly for internal use
	public static function unique_salt() {
		return substr(sha1(mt_rand()), 0, 22);
	}

	// This will be used to generate a hash
	public static function hash($password) {
		return crypt($password, self::$algo . self::$cost . '$' . self::unique_salt());
	}

	// This will be used to compare a password against a hash
	public static function check_password($hash, $password) {
		$full_salt = substr($hash, 0, 29);
		$new_hash = crypt($password, $full_salt);
		return ($hash == $new_hash);
	}
}

?>