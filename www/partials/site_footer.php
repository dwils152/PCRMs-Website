<?php
$currentYear = date('Y');
$visitorCount = isset($visitorCount) ? $visitorCount : null;
?>
<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__about">
            <a class="site-footer__brand" href="index.php">PCRMs</a>
            <p>A research resource for predicted cis-regulatory modules and their constituent transcription factor binding sites.</p>
            <a class="site-footer__affiliation" href="https://sulab.charlotte.edu/" target="_blank" rel="noopener noreferrer">
                <img src="images/logo-unc-charlotte-transparent.png" alt="UNC Charlotte">
                <span>Developed by the Su Lab</span>
            </a>
        </div>

        <nav class="site-footer__links" aria-label="Resource links">
            <div>
                <h2>Explore</h2>
                <a href="browse_dataset.php">Browse datasets</a>
                <a href="detailed_search.php">Analysis tools</a>
                <a href="download_data.php">Downloads</a>
            </div>
            <div>
                <h2>Research</h2>
                <a href="https://doi.org/10.1093/database/baac024" target="_blank" rel="noopener noreferrer">PCRMs publication</a>
                <a href="https://doi.org/10.1093/nargab/lqab052" target="_blank" rel="noopener noreferrer">dePCRM2 method</a>
                <a href="lqab052.pdf">Read the method PDF</a>
            </div>
        </nav>
    </div>

    <div class="site-footer__meta">
        <p>© 2020–<?php echo $currentYear; ?> The Su Lab at UNC Charlotte.</p>
        <?php if ($visitorCount !== null): ?>
            <p><?php echo number_format((int) $visitorCount); ?> recorded visits</p>
        <?php endif; ?>
    </div>
</footer>
