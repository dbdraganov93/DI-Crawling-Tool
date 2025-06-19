<?php

namespace App\Service;

use TCPDF2DBarcode;

class TwoFactorService
{
    private const TOTP_PERIOD = 30;
    private const TOTP_DIGITS = 6;

    public function generateSecret(int $length = 16): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; ++$i) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $secret;
    }

    public function getOtpAuthUrl(string $email, string $secret, string $issuer = 'DICrawling'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer)
        );
    }

    public function generateQrCode(string $otpauthUrl): string
    {
        $barcode = new TCPDF2DBarcode($otpauthUrl, 'QRCODE,M');
        $pngData = $barcode->getBarcodePngData(4, 4, [0, 0, 0]);

        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $timeSlice = (int) floor(time() / self::TOTP_PERIOD);

        foreach ([-1, 0, 1] as $offset) {
            if ($this->calculateCode($secret, $timeSlice + $offset) === $code) {
                return true;
            }
        }

        return false;
    }

    private function calculateCode(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = substr($hash, $offset, 4);
        $value = unpack('N', $truncatedHash)[1] & 0x7fffffff;

        return str_pad((string) ($value % (10 ** self::TOTP_DIGITS)), self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $secret = str_replace('=', '', $secret);
        $bits = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $val = strpos($map, $secret[$i]);
            if ($val === false) {
                throw new \InvalidArgumentException('Invalid Base32 character.');
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $byte = substr($bits, $i, 8);
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
