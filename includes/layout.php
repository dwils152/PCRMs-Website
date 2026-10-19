<?php

require_once __DIR__ . '/catalog.php';

if (!function_exists('pcrms_url')) {
    // A site path that also works from error pages served for nested URLs:
    // 'css/modern.css' becomes '/css/modern.css', or '/pcrms/css/modern.css'
    // when the site lives in a subdirectory.
    function pcrms_url($path)
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/';
        $base = rtrim(dirname($script), '/') . '/';
        return $base . $path;
    }
}

if (!function_exists('pcrms_asset')) {
    // Adds a version stamp so browsers reload changed CSS and JavaScript.
    function pcrms_asset($path)
    {
        $file = dirname(__DIR__) . '/www/' . $path;
        return pcrms_url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}

if (!function_exists('pcrms_svg')) {
    // Returns an inline SVG from includes/svg, e.g. pcrms_svg('organisms/hs').
    function pcrms_svg($name)
    {
        if (!is_string($name) || !preg_match('/^[a-z]+\/[a-z0-9-]+$/', $name)) {
            return '';
        }
        $path = __DIR__ . '/svg/' . $name . '.svg';
        return is_file($path) ? trim(file_get_contents($path)) : '';
    }
}

if (!function_exists('pcrms_page_start')) {
    /**
     * Opens a page: document head, skip link and site header.
     *
     * Options: title (page name, or null for the home page), description,
     * active (home, browse, analysis, downloads).
     */
    function pcrms_page_start($page = [])
    {
        $title = isset($page['title']) && $page['title'] !== ''
            ? $page['title'] . ' — PCRMs'
            : 'PCRMs — Predicted cis-Regulatory Modules';
        $description = isset($page['description'])
            ? $page['description']
            : 'PCRMs is a database of predicted cis-regulatory modules and their transcription factor binding sites in the human and mouse genomes.';
        $activePage = isset($page['active']) ? $page['active'] : '';
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo pcrms_h($title); ?></title>
    <meta name="description" content="<?php echo pcrms_h($description); ?>">
    <meta name="theme-color" content="#17694B">
    <script>document.documentElement.className += ' js';</script>
    <link rel="icon" href="<?php echo pcrms_h(pcrms_url('images/brand/favicon.svg')); ?>" type="image/svg+xml">
    <link rel="icon" href="<?php echo pcrms_h(pcrms_url('images/brand/favicon-32.png')); ?>" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo pcrms_h(pcrms_url('images/brand/apple-touch-icon.png')); ?>">
    <link rel="preload" href="<?php echo pcrms_h(pcrms_url('fonts/hanken-grotesk.woff2')); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?php echo pcrms_h(pcrms_asset('css/modern.css')); ?>">
</head>
<body>
<?php
        require __DIR__ . '/partials/site_header.php';
    }
}

if (!function_exists('pcrms_page_end')) {
    // Closes a page: site footer and scripts (paths relative to www/).
    function pcrms_page_end($scripts = [])
    {
        require __DIR__ . '/partials/site_footer.php';
        echo '<script src="' . pcrms_h(pcrms_asset('js/site.js')) . '" defer></script>' . "\n";
        foreach ($scripts as $script) {
            echo '<script src="' . pcrms_h(pcrms_asset($script)) . '" defer></script>' . "\n";
        }
        echo "</body>\n</html>\n";
    }
}
