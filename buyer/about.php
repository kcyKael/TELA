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
                <p class="text-muted mb-4">The development team responsible for planning, building, testing, and integrating the TELA system.</p>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="member-entry h-100">
                            <h3 class="h5 mb-1">Kyl Aldric Valencia</h3>
                            <p class="member-role mb-3">Project Lead, Full-Stack Developer, and System Architect</p>
                            <ul class="member-responsibilities mb-0">
                                <li>Led the overall planning, architecture, and implementation of the TELA system.</li>
                                <li>Designed and developed most of the application using procedural PHP, MySQLi, SQL, Bootstrap 5, CSS, and JavaScript.</li>
                                <li>Implemented authentication, email verification, shopping cart, checkout, buyer order history, admin user and order management, inventory management, and audit logging.</li>
                                <li>Designed the database schema, business logic, and security features, including CSRF protection, prepared statements, session security, transaction handling, ownership validation, and stock management.</li>
                                <li>Integrated all modules and performed debugging, optimization, UI refinement, documentation, and final deployment preparation.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="member-entry h-100">
                            <h3 class="h5 mb-1">Emran Ryan Maina</h3>
                            <p class="member-role mb-3">Backend Developer and Database Developer</p>
                            <ul class="member-responsibilities mb-0">
                                <li>Assisted in developing PHP backend modules and reusable functions.</li>
                                <li>Helped implement SQL queries, database integration, and CRUD operations for selected features.</li>
                                <li>Assisted in testing and refining authentication, user management, and database validation.</li>
                                <li>Helped verify business logic and backend functionality during development.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="member-entry h-100">
                            <h3 class="h5 mb-1">Jerahmeel Romero</h3>
                            <p class="member-role mb-3">Frontend and Product Module Developer</p>
                            <ul class="member-responsibilities mb-0">
                                <li>Assisted in developing frontend pages using HTML, Bootstrap 5, CSS, and JavaScript.</li>
                                <li>Helped implement product and category management interfaces.</li>
                                <li>Assisted in integrating frontend pages with PHP backend functionality.</li>
                                <li>Helped improve responsive layouts, UI consistency, and form validation.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="member-entry h-100">
                            <h3 class="h5 mb-1">Xander David Canlas</h3>
                            <p class="member-role mb-3">Quality Assurance and Integration Developer</p>
                            <ul class="member-responsibilities mb-0">
                                <li>Assisted in integrating PHP modules and verifying database interactions.</li>
                                <li>Performed functional testing of Buyer and Admin workflows.</li>
                                <li>Tested SQL operations, form validation, session behavior, and application security.</li>
                                <li>Identified development issues, verified fixes, and conducted final regression testing before submission.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
