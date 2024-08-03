<?php

namespace App\Encryption;



use Illuminate\Support\Facades\Log;

class AsymmetricEncryption
{
    public static function decryptAsymmetric($encryptedData): string
    {
        $privateKeyPath = storage_path('/app/keys/private.key');
        Log::info("info",["privateKeyPath: " => $privateKeyPath]);
        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));
        openssl_private_decrypt(base64_decode($encryptedData), $decryptedData, $privateKey);
        Log::info("error",["encryptedData: " => $decryptedData]);
        return $decryptedData;
    }

    public static function encryptAsymmetric($data)
    {
        $publicKey = storage_path('app/keys/public.key');
        openssl_public_encrypt($data, $encrypted, openssl_pkey_get_public(file_get_contents($publicKey)));
        return base64_encode($encrypted);
    }

}
