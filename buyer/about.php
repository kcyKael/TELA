<?php
$pageTitle = 'About TELA';
$activePage = 'about';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel about-panel">
            <img class="about-logo mb-3" src="<?php echo BASE_URL; ?>assets/images/Whole_logo-tela_icon-and-text.png" alt="TELA Technology Enhanced Lifestyle Apparel logo">
            <p class="section-label mb-2">About TELA</p>
            <h1 class="h3 mb-3">Technology Enhanced Lifestyle Apparel</h1>
            <p class="lead">TELA is an educational online store focused exclusively on Hoodies.</p>
            <p class="text-muted mb-4">
                The project demonstrates product browsing, secure buyer accounts, cart and checkout workflows, order history, and administrative management using procedural PHP and MySQL.
            </p>

            <section class="border-top pt-4 mb-4" aria-labelledby="projectContextHeading">
                <h2 class="h5 mb-3" id="projectContextHeading">Project Context</h2>
                <p class="mb-2">
                    Technology Enhanced Lifestyle Apparel combines a focused clothing-store concept with the practical application of web-development lessons.
                </p>
                <p class="mb-0">
                    TELA was created by <?php echo escapeOutput(GROUP_NAME); ?> as a college final project. Payment options shown by the website are classroom simulations; the site does not process real payments or provide commercial shipping and delivery tracking.
                </p>
            </section>

            <section class="border-top pt-4" aria-labelledby="groupMembersHeading">
                <h2 class="h5 mb-2" id="groupMembersHeading">Group Members</h2>
                <p class="text-muted mb-3">Replace these approved placeholders with the final group details before submission screenshots.</p>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="member-entry h-100">
                            <p class="fw-semibold mb-1">[Member 1 Name]</p>
                            <p class="text-muted mb-0">[Role / Contribution]</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="member-entry h-100">
                            <p class="fw-semibold mb-1">[Member 2 Name]</p>
                            <p class="text-muted mb-0">[Role / Contribution]</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="member-entry h-100">
                            <p class="fw-semibold mb-1">[Member 3 Name]</p>
                            <p class="text-muted mb-0">[Role / Contribution]</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="member-entry h-100">
                            <p class="fw-semibold mb-1">[Member 4 Name]</p>
                            <p class="text-muted mb-0">[Role / Contribution]</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
