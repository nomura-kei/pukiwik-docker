<?php

/**
 * 指定された連想配列中の $key に対応する値を取得します。
 * 対応する値がない場合、$default に指定された値が返されます。
 *
 * @param $array 連想配列
 * @param $key   キー
 * @param $default デフォルト値
 * @return キーに対応する値
 */
function getValue($array, $key, $default=0)
{
	return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * BASE32 Encodeer.
 * @see RFC 4648
 */
final class Base32
{
	private $map = [
		'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
		'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
		'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
		'Y', 'Z', '2', '3', '4', '5', '6', '7',
		'=',				// padding char
	];

	private $padLenMap = [
		 8 => 6,
		16 => 4,
		24 => 3,
		32 => 1,
	];

	public function encode(string $input): string
	{
		if ($input === '')
		{
			return '';
		}

		$inputArray = str_split($input);
		$inputArrayLen = count($inputArray);
		$binStr = '';

		for ($i = 0; $i < $inputArrayLen; $i++)
		{
			$binStr .= str_pad(base_convert((string) ord($input[$i]), 10, 2), 8, '0', STR_PAD_LEFT);
		}
		$binArray5 = str_split($binStr, 5);
		$binArray5Len = count($binArray5);

		$result = '';
		$i = 0;
		while ($i < $binArray5Len)
		{
			$result .= $this->map[base_convert(str_pad($binArray5[$i], 5, '0'), 2, 10)];
			$i++;
		}

		$x = strlen($binStr) % 40;
		$padLen = getValue($this->padLenMap, $x, 0);
		$result = $result . str_repeat($this->map[32], $padLen);

		return $result;
	}
}



/**
 * TOTP 認証
 */
final class Totp
{
	/** ステップ秒数。	*/
	private $step = 30;

	/** 鍵となる値。	*/
	private $secret;


	/**
	 * TOTP を構築します。
	 * TOTP の検証時には、$secret を指定して構築する必要があります。
	 *
	 * @param $secret BASE32によりエンコードされた鍵
	 */
	public function __construct($secret = NULL)
	{

		$this->secret= is_null($secret) ? $this->newSecret() : $secret;
	}


	/**
	 * 指定された発行者とアカウントの OTP(One Time Password) 用の
	 * Auth URI を生成します。
	 *
	 * [参照]
	 * https://github.com/google/google-authenticator/wiki/Key-Uri-Format
	 *
	 * @param issuer 発行者
	 * @param accountName アカウント名
	 * @param type タイプ (totp or hotp)
	 * @return OTP Auth URI
	 */
	public function generateUri(string $issuer, string $accountName, string $type = 'totp'): string
	{
		$issuerUri = rawurlencode($issuer);
		$accountNameUri = rawurlencode($accountName);
		$label = "{$issuerUri}:{$accountNameUri}";
		$parameters = "secret={$this->secret}&issuer={$issuerUri}&algorithm=SHA1&digits=6&period={$this->step}";
		$uri = "otpauth://{$type}/{$label}?{$parameters}";
		return $uri;
	}


	/**
	 * 鍵を取得します。
	 *
	 * @return BASE32 にてエンコードされた鍵
	 */
	public function getSecret(): string
	{
		return $this->secret;
	}


	/**
	 * 指定された TOTP のコードが正しいか否か判定します。
	 *
	 * @param $code 6桁のコード
	 * @return true/false (正しい/不正)
	 */
	public function isValidTotp($code): bool
	{
		$step = $this->getCurrentStep();

		# 現在の時刻 ±1ステップ(30秒) のズレは許容する。
		$totpPrev = $this->generateTotp($step -1);
		$totpNow  = $this->generateTotp($step);
		$totpNext = $this->generateTotp($step + 1);

		$exp = [ $totpPrev, $totpNow, $totpNext ];
		$result = in_array($code, $exp);
		return $result;
	}

	/**
	 * 指定された HOTP のコードが正しいか否か判定します。
	 *
	 * @param $code 6桁のコード
	 * @param $counter カウンタ (8 byte のバイト列)
	 * @return true/false (正しい/不正)
	 */
	public function isValidHotp($code, $counter): bool
	{
		$exp = $this->generateHotp($counter);
		return ($exp === $code);
	}

	/**
	 * 指定されたステップの TOTP を生成します。
	 *
	 * @param $step ステップ
	 * @return TOTP
	 */
	public function generateTotp(int $step = NULL): string
	{
		if ($step === NULL)
		{
			$step = $this->getCurrentStep();
		}
		$stepBytes = $this->int64ToBytes($step);
		$otpStr = $this->generateHotp($stepBytes);
		return $otpStr;
	}

	/**
	 * 指定されたカウンタの HOTP を生成します。
	 *
	 * @param $counter カウンタ
	 *
	 */
	public function generateHotp($counter): string
	{
		$digest = hash_hmac('sha1', $counter, $this->secret, true);
		$otp = $this->dynamicTruncate($digest) % 1000000;
		$otpStr = str_pad($otp, 6, '0', STR_PAD_LEFT);
		return $otpStr;
	}


	////////////////////////////////////////////////////////////////////////////
	//
	// private method
	//

	/**
	 * 新たな鍵を生成します。
	 *
	 * @return 生成した鍵
	 */
	private function newSecret()
	{
		$base32 = new Base32();
		$seed = random_bytes(20);
		$seedBase32 = $base32->encode($seed);
		return $seedBase32;
	}

	private function dynamicTruncate(string $digest): int
	{
		$offset = ord($digest[19]) & 0x0f;
		$binary = (
			ord($digest[$offset    ]) << 24 |
			ord($digest[$offset + 1]) << 16 |
			ord($digest[$offset + 2]) <<  8 |
			ord($digest[$offset + 3])
		);
		$binaryMasked = $binary & 0x7fffffff;
		return $binaryMasked;
	}

	/**
	 * 現在のステップを返します。
	 *
	 * @return 現在のステップ
	 */
	private function getCurrentStep(): int
	{
		return intdiv(time(), $this->step);
	}
	
	/**
	 * 指定された 64 ビットの値をバイト列に変換します。
	 * @param num 数値
	 * @return バイト列
	 */
	private function int64ToBytes(int $num): string
	{
		$bytes = '';
		$bytes[0] = chr($num >> 56 & 0xff);
		$bytes[1] = chr($num >> 48 & 0xff);
		$bytes[2] = chr($num >> 40 & 0xff);
		$bytes[3] = chr($num >> 32 & 0xff);
		$bytes[4] = chr($num >> 24 & 0xff);
		$bytes[5] = chr($num >> 16 & 0xff);
		$bytes[6] = chr($num >>  8 & 0xff);
		$bytes[7] = chr($num       & 0xff);
		return $bytes;
	}

}


$totp = new Totp();
$uri = $totp->generateUri("ehobby.mydns.jp", "sample@gmail.com", 'totp');
print($uri);
$secret = $totp->getSecret();


$counter = "\0\0\0\0\0\0\0\1";
$hotpCode = $totp->generateHotp($counter);
$totpCode = $totp->generateTotp();

print "HOTP $hotpCode";
print "TOTP $totpCode";

$res = $totp->isValidHotp($hotpCode, $counter);
print "HOTP isValid : $res";

$res = $totp->isValidTotp($totpCode);
print "TOTP isValid : $res";


?>
