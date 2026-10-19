<?php
require_once dirname(__DIR__) . '/includes/layout.php';

pcrms_page_start([
    'title' => 'Tools',
    'description' => 'Run the Su Lab’s analysis tools, such as the ProSampler motif finder, on your own data.',
    'active' => 'tools',
]);
?>
<main id="main-content">
    <section class="band" aria-labelledby="page-title">
        <div class="band__inner shell">
            <h1 id="page-title">Tools</h1>
            <p class="band__lede">Run the Su Lab’s analysis tools on your own data.</p>
        </div>
    </section>

    <div class="page-main shell stack">
        <section class="card" aria-labelledby="prosampler-title">
            <div class="tool-card__head">
                <span class="tool-tab__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 18V9M8 18V5M12 18v-6M16 18V8M20 18v-4M3 21h18"/></svg></span>
                <div>
                    <h2 class="card__title" id="prosampler-title">ProSampler <span class="badge">Coming soon</span></h2>
                    <p class="tool-card__sub">Find the motifs in your ChIP-seq peaks</p>
                </div>
            </div>
            <p class="tool-card__text">ProSampler is the motif finder used to build the PCRMs maps. It handles very large ChIP-seq datasets quickly, and works out by itself how many motifs there are and how long each one is.</p>
            <dl class="tool-io">
                <div><dt>You upload</dt><dd>Peak sequences in FASTA format</dd></div>
                <div><dt>You get</dt><dd>The motifs found, as logos and a MEME-format file</dd></div>
            </dl>
            <div class="button-row">
                <a class="pill-link" href="https://github.com/zhengchangsulab/prosampler">
                    <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z"/></svg>
                    ProSampler on GitHub
                </a>
                <a class="pill-link" href="https://doi.org/10.1093/bioinformatics/btz290">Li et al., <em>Bioinformatics</em> 2019</a>
            </div>
            <p class="note">Until the online version is ready, you can download ProSampler and run it on your own computer.</p>
        </section>
    </div>
</main>
<?php pcrms_page_end(); ?>
