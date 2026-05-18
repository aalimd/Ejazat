<?php
require_once 'includes/TotpHelper.php';

echo "Testing TOTP Helper...\n";

// 1. Generate Secret
$secret = TotpHelper::generateSecret();
echo "Generated Secret: " . $secret . "\n";

// 2. Decode Secret
$decoded = TotpHelper::base32Decode($secret);
if ($decoded === false) {
    echo "FAIL: Base32 Decode failed!\n";
    exit(1);
}
echo "Base32 Decode: SUCCESS\n";

// 3. Get Code
$code = TotpHelper::getCode($secret);
echo "Current 6-Digit Code: " . $code . "\n";

if (strlen($code) !== 6 || !is_numeric($code)) {
    echo "FAIL: Code is not a 6-digit numeric string!\n";
    exit(1);
}
echo "Code generation: SUCCESS\n";

// 4. Verify Code
$verify_success = TotpHelper::verifyCode($secret, $code);
if (!$verify_success) {
    echo "FAIL: Code verification failed!\n";
    exit(1);
}
echo "Code verification (current slice): SUCCESS\n";

// 5. Verify past and future code (discrepancy)
$prev_slice = floor(time() / 30) - 1;
$prev_code = TotpHelper::getCode($secret, $prev_slice);
$verify_prev = TotpHelper::verifyCode($secret, $prev_code, 1);
if (!$verify_prev) {
    echo "FAIL: Code verification with drift failed!\n";
    exit(1);
}
echo "Code verification with drift (previous slice): SUCCESS\n";

echo "ALL TESTS PASSED SUCCESSFULLY! 🚀\n";
