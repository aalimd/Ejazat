<?php
/**
 * TOTP (Time-based One-Time Password) Helper Class
 * Compatible with Google Authenticator, Authy, Microsoft Authenticator, etc.
 * Compliant with RFC 6238.
 */
class TotpHelper {
    private static $_lut = [
        'A' => 0,  'B' => 1,  'C' => 2,  'D' => 3,  'E' => 4,  'F' => 5,  'G' => 6,  'H' => 7,
        'I' => 8,  'J' => 9,  'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
        'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
        'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
    ];

    /**
     * Generate a random Base32 secret key.
     */
    public static function generateSecret($length = 16) {
        $b32 = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $secret = "";
        for ($i = 0; $i < $length; $i++) {
            $secret .= $b32[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Decode Base32 string into binary data.
     */
    public static function base32Decode($secret) {
        $secret = strtoupper($secret);
        if (!preg_match('/^[A-Z2-7]+$/', $secret)) {
            return false;
        }

        $buf = '';
        $val = 0;
        $bits = 0;
        
        $len = strlen($secret);
        for ($i = 0; $i < $len; $i++) {
            $val = ($val << 5) + self::$_lut[$secret[$i]];
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $buf .= chr(($val >> $bits) & 255);
            }
        }
        return $buf;
    }

    /**
     * Generate a 6-digit TOTP code for a secret and timeslice.
     */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        if ($secretKey === false) return false;

        // Pack time slice into a 64-bit binary representation (8 bytes)
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        
        // HMAC-SHA1
        $hm = hash_hmac('sha1', $time, $secretKey, true);
        
        // Dynamic truncation
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        
        // Unpack value
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        
        return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a TOTP code against a secret key with a specified time drift window (discrepancy * 30s).
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        // Allow user a drift window of discrepancy * 30s before/after
        for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate QR Code URL via QR Server.
     */
    public static function getQrUrl($name, $secret, $title = 'HR-App') {
        $otpauth = "otpauth://totp/" . urlencode($title . ":" . $name) . "?secret=" . $secret . "&issuer=" . urlencode($title);
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth);
    }
}
