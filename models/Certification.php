<?php


class Certification
{
    public static function generateCertificate($data)
    {
        // Logic to generate a certificate based on provided data
        return "Certificate generated for " . $data['name'];
    }

    public static function getCertificateById($uuid)
    {
        // Logic to retrieve a certificate by its ID
        return "Certificate details for ID: " . $uuid;
    }
}
