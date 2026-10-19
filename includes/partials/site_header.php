<?php
$activePage = isset($activePage) ? $activePage : '';

if (!function_exists('pcrms_nav_link')) {
    function pcrms_nav_link($page, $activePage, $href, $label, $iconPath)
    {
        $current = $page === $activePage;
        echo '<a class="site-nav__link' . ($current ? ' is-active' : '') . '" href="' . pcrms_h(pcrms_url($href)) . '"'
            . ($current ? ' aria-current="page"' : '') . '>'
            . '<svg class="site-nav__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $iconPath . '</svg>'
            . pcrms_h($label) . '</a>';
    }
}
?>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header">
    <div class="site-header__inner shell">
        <a class="site-brand" href="<?php echo pcrms_h(pcrms_url('index.php')); ?>">
            <img src="<?php echo pcrms_h(pcrms_url('images/brand/pcrms-logo.svg')); ?>" alt="PCRMs, Predicted cis-Regulatory Modules — home" width="291" height="60">
        </a>

        <button class="site-nav__toggle" type="button" aria-expanded="false" aria-controls="site-navigation">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <span>Menu</span>
        </button>

        <nav class="site-nav" id="site-navigation" aria-label="Primary">
            <?php
            pcrms_nav_link('home', $activePage, 'index.php', 'Home', '<path d="M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z"/>');
            pcrms_nav_link('browse', $activePage, 'browse_dataset.php', 'Browse', '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>');
            pcrms_nav_link('analysis', $activePage, 'detailed_search.php', 'Functional analysis', '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>');
            pcrms_nav_link('tools', $activePage, 'tools.php', 'Tools', '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9z"/>');
            pcrms_nav_link('downloads', $activePage, 'download_data.php', 'Download', '<path d="M12 3v12m-5-5 5 5 5-5M4 21h16"/>');
            ?>
        </nav>
    </div>
</header>
