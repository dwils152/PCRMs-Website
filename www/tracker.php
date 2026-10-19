<?php

require_once __DIR__ . '/connection.php';

$trackerAutoload = __DIR__ . '/tracker/vendor/autoload.php';
if (is_file($trackerAutoload)) {
    require_once $trackerAutoload;
}

if (!function_exists('pcrms_visitor_ip')) {
    function pcrms_visitor_ip()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
}

if (!function_exists('pcrms_ip_location')) {
    function pcrms_ip_location($ip)
    {
        $fallback = ['country' => 'N/A', 'city' => 'N/A'];

        if ($ip === '::1' || $ip === '127.0.0.1') {
            return ['country' => 'USA', 'city' => 'Charlotte'];
        }

        if (!class_exists('GeoIp2\\Database\\Reader')) {
            return $fallback;
        }

        try {
            $countryDatabase = __DIR__ . '/tracker/geo_data/GeoLite2-Country_20220617/GeoLite2-Country.mmdb';
            $cityDatabase = __DIR__ . '/tracker/geo_data/GeoLite2-City_20220617/GeoLite2-City.mmdb';
            $countryReader = new GeoIp2\Database\Reader($countryDatabase);
            $cityReader = new GeoIp2\Database\Reader($cityDatabase);
            $countryRecord = $countryReader->country($ip);
            $cityRecord = $cityReader->city($ip);

            return [
                'country' => $countryRecord->country->name ?: 'N/A',
                'city' => $cityRecord->city->name ?: 'N/A',
            ];
        } catch (Throwable $exception) {
            return $fallback;
        }
    }
}

try {
    $visitorIp = pcrms_visitor_ip();
    $visitorLocation = pcrms_ip_location($visitorIp);
    $visitedAt = date('Y-m-d H:i:s');

    $visitorStatement = $connection->prepare(
        'INSERT INTO visitors VALUES (DEFAULT, ?, ?, ?, ?)'
    );
    $visitorStatement->bind_param(
        'ssss',
        $visitorIp,
        $visitorLocation['country'],
        $visitorLocation['city'],
        $visitedAt
    );
    $visitorStatement->execute();
    $visitorStatement->close();
} catch (Throwable $exception) {
    error_log('PCRMs visitor tracking failed: ' . $exception->getMessage());
}
