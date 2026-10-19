<?php
$activePage = isset($activePage) ? $activePage : '';

if (!function_exists('pcrms_nav_class')) {
    function pcrms_nav_class($page, $activePage)
    {
        return $page === $activePage ? 'site-nav__link is-active' : 'site-nav__link';
    }
}

if (!function_exists('pcrms_aria_current')) {
    function pcrms_aria_current($page, $activePage)
    {
        return $page === $activePage ? ' aria-current="page"' : '';
    }
}
?>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header">
    <div class="site-header__inner">
        <a class="site-brand" href="index.php" aria-label="PCRMs home">
            <span class="site-brand__mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" role="img">
                    <path d="M12 7c12 0 12 10 24 10M12 31c12 0 12 10 24 10M12 7c0 17 24 17 24 34M36 7c0 17-24 17-24 34" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    <path d="M15 15h18M15 33h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".55"/>
                </svg>
            </span>
            <span class="site-brand__copy">
                <strong>PCRMs</strong>
                <span>Predicted regulatory modules</span>
            </span>
        </a>

        <button class="site-nav__toggle" type="button" aria-expanded="false" aria-controls="site-navigation">
            <span class="sr-only">Toggle navigation</span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </button>

        <nav class="site-nav" id="site-navigation" aria-label="Primary navigation">
            <a class="<?php echo pcrms_nav_class('home', $activePage); ?>" href="index.php"<?php echo pcrms_aria_current('home', $activePage); ?>>Home</a>
            <a class="<?php echo pcrms_nav_class('browse', $activePage); ?>" href="browse_dataset.php"<?php echo pcrms_aria_current('browse', $activePage); ?>>Browse</a>

            <details class="site-nav__group<?php echo $activePage === 'analysis' ? ' is-active' : ''; ?>">
                <summary<?php echo pcrms_aria_current('analysis', $activePage); ?>>Analysis <span aria-hidden="true">⌄</span></summary>
                <div class="site-nav__submenu">
                    <a href="detailed_search.php">
                        <strong>All analysis tools</strong>
                        <span>Choose a gene- or TF-based workflow</span>
                    </a>
                    <a href="DS_get_genes_closest_crm.php">
                        <strong>Closest CRM</strong>
                        <span>Find the nearest module to a gene</span>
                    </a>
                    <a href="DS_get_crm_in_genes_range.php">
                        <strong>CRMs around a gene</strong>
                        <span>Search a defined genomic range</span>
                    </a>
                    <a href="DS_get_tf.php">
                        <strong>TF binding sites</strong>
                        <span>Find sites associated with a TF</span>
                    </a>
                </div>
            </details>

            <a class="<?php echo pcrms_nav_class('downloads', $activePage); ?>" href="download_data.php"<?php echo pcrms_aria_current('downloads', $activePage); ?>>Downloads</a>
            <a class="site-nav__link site-nav__link--external" href="https://sulab.charlotte.edu/" target="_blank" rel="noopener noreferrer">
                Su Lab <span aria-hidden="true">↗</span>
            </a>
        </nav>
    </div>
</header>
<script src="js/site.js" defer></script>
