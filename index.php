<?php
/**
 * CampusFind Pro - Landing Page
 */
require_once 'config/config.php';
require_once 'config/session.php';
require_once 'config/database.php';

$page_title = 'Welcome';

// Fetch dynamic statistics from DB
$total_lost = 0;
$total_found = 0;
$resolved_claims = 0;
$total_users = 0;

try {
    $db = Database::getInstance()->getConnection();
    
    $lost_stmt = $db->query("SELECT COUNT(*) as count FROM lost_items WHERE status = 'lost'");
    $total_lost = $lost_stmt->fetch()['count'];

    $found_stmt = $db->query("SELECT COUNT(*) as count FROM found_items WHERE status = 'found'");
    $total_found = $found_stmt->fetch()['count'];

    $claims_stmt = $db->query("SELECT COUNT(*) as count FROM claims WHERE status = 'approved'");
    $resolved_claims = $claims_stmt->fetch()['count'];

    $users_stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $total_users = $users_stmt->fetch()['count'];

    // Fetch latest items for preview
    $latest_lost_stmt = $db->query("SELECT l.*, c.name as category_name FROM lost_items l JOIN categories c ON l.category_id = c.id WHERE l.status = 'lost' ORDER BY l.created_at DESC LIMIT 3");
    $latest_lost = $latest_lost_stmt->fetchAll();

    $latest_found_stmt = $db->query("SELECT f.*, c.name as category_name FROM found_items f JOIN categories c ON f.category_id = c.id WHERE f.status = 'found' ORDER BY f.created_at DESC LIMIT 3");
    $latest_found = $latest_found_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Landing Page Fetch Fail: " . $e->getMessage());
    $latest_lost = [];
    $latest_found = [];
}

// Load navigation header
require_once 'includes/navbar.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-gradient-orb-1"></div>
    <div class="hero-gradient-orb-2"></div>
    
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3 fw-600">
                    <i class="fa-solid fa-sparkles me-2"></i>Reuniting Campus Belongings Instantly
                </div>
                <h1 class="hero-title">
                    Lost something? <br>
                    Let's help you <span>Find it.</span>
                </h1>
                <p class="text-secondary fs-5 mb-4" style="line-height: 1.6;">
                    CampusFind Pro is the university's premier ecosystem for Lost & Found items. Report items, browse verified claims, and track returns securely.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <?php if (isLoggedIn()): ?>
                        <a href="<?php echo SITE_URL; ?>/lost/report.php" class="btn btn-premium px-4 py-3"><i class="fa-solid fa-plus me-2"></i>Report Lost Item</a>
                        <a href="<?php echo SITE_URL; ?>/found/report.php" class="btn btn-premium-outline px-4 py-3"><i class="fa-solid fa-hand-holding-hand me-2"></i>Report Found Item</a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-premium px-4 py-3"><i class="fa-solid fa-right-to-bracket me-2"></i>Get Started Now</a>
                        <a href="<?php echo SITE_URL; ?>/lost/search.php" class="btn btn-premium-outline px-4 py-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Database</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-lg-6" data-aos="fade-left">
                <!-- Landing Page Search Box (Glassmorphic Widget) -->
                <div class="glass-panel p-4 p-md-5">
                    <h4 class="font-heading fw-700 mb-3"><i class="fa-solid fa-magnifying-glass text-primary me-2"></i>Instant Search</h4>
                    <p class="text-muted" style="font-size: 0.9rem;">Query our database of verified items reported by security and students.</p>
                    
                    <form action="<?php echo SITE_URL; ?>/lost/search.php" method="GET" class="mt-4">
                        <div class="mb-3">
                            <label class="form-label text-secondary fw-500">What are you looking for?</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 border-color"><i class="fa-solid fa-tag text-muted"></i></span>
                                <input type="text" name="q" class="form-control form-premium-control border-start-0 ps-0" placeholder="e.g. MacBook Pro, Blue Backpack, Car Keys" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="form-label text-secondary fw-500">I want to search:</label>
                                <select id="searchType" class="form-select form-premium-control" onchange="this.form.action = '<?php echo SITE_URL; ?>/' + this.value + '/search.php'">
                                    <option value="lost">Lost Database</option>
                                    <option value="found">Found Database</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-secondary fw-500">Category</label>
                                <select name="category" class="form-select form-premium-control">
                                    <option value="">All Categories</option>
                                    <?php
                                    try {
                                        $cat_stmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
                                        while ($cat = $cat_stmt->fetch()) {
                                            echo "<option value='".sanitize($cat['name'])."'>".sanitize($cat['name'])."</option>";
                                        }
                                    } catch(Exception $e){}
                                    ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-premium w-100 py-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Database</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Grid -->
<section class="py-5 bg-opacity-25" style="background: var(--bg-secondary);">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-3 col-6" data-aos="zoom-in">
                <div class="glass-panel p-4">
                    <div class="fs-1 fw-800 text-primary mb-1"><?php echo $total_lost; ?></div>
                    <div class="text-secondary fw-600">Active Lost Reports</div>
                </div>
            </div>
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="100">
                <div class="glass-panel p-4">
                    <div class="fs-1 fw-800 text-primary mb-1"><?php echo $total_found; ?></div>
                    <div class="text-secondary fw-600">Items Found</div>
                </div>
            </div>
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="200">
                <div class="glass-panel p-4">
                    <div class="fs-1 fw-800 text-success mb-1"><?php echo $resolved_claims; ?></div>
                    <div class="text-secondary fw-600">Claims Resolved</div>
                </div>
            </div>
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="300">
                <div class="glass-panel p-4">
                    <div class="fs-1 fw-800 text-primary mb-1"><?php echo $total_users; ?></div>
                    <div class="text-secondary fw-600">Active Students</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Latest Items Section -->
<section class="py-5 my-3">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-5">
            <div data-aos="fade-right">
                <h2 class="font-heading fw-800 mb-2">Recently Reported Lost Items</h2>
                <p class="text-secondary m-0">Can you help locate these items on campus?</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/lost/search.php" class="btn btn-premium-outline d-none d-md-block" data-aos="fade-left">Browse All Lost Items <i class="fa-solid fa-arrow-right ms-2"></i></a>
        </div>
        
        <div class="row g-4">
            <?php if (empty($latest_lost)): ?>
                <div class="col-12 text-center py-5" data-aos="fade-up">
                    <div class="glass-panel p-5">
                        <i class="fa-solid fa-clipboard-question text-muted fs-1 mb-3"></i>
                        <h5 class="text-secondary">No active lost items reported.</h5>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($latest_lost as $item): ?>
                    <div class="col-lg-4 col-md-6" data-aos="fade-up">
                        <div class="glass-card h-100 position-relative">
                            <span class="item-badge badge-lost">Lost</span>
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image'] ?: 'default-item.png'; ?>" class="w-100" style="height: 220px; object-fit: cover;" alt="<?php echo sanitize($item['title']); ?>">
                            
                            <div class="p-4">
                                <span class="badge bg-secondary text-secondary mb-2" style="font-size: 0.8rem;"><?php echo sanitize($item['category_name']); ?></span>
                                <h5 class="font-heading fw-700 mb-2"><?php echo sanitize($item['title']); ?></h5>
                                <p class="text-secondary text-truncate-2 mb-3" style="font-size: 0.9rem;"><?php echo sanitize($item['description']); ?></p>
                                
                                <div class="d-flex flex-column gap-2 mb-4" style="font-size: 0.85rem; color: var(--text-muted);">
                                    <div><i class="fa-solid fa-location-dot me-2 text-primary"></i><?php echo sanitize($item['location']); ?></div>
                                    <div><i class="fa-regular fa-calendar me-2 text-primary"></i>Lost: <?php echo formatDate($item['lost_date']); ?></div>
                                    <?php if ($item['reward'] > 0): ?>
                                        <div class="fw-700 text-success"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Reward: $<?php echo number_format($item['reward'], 2); ?></div>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/lost/view.php?id=<?php echo $item['id']; ?>" class="btn btn-premium w-100">View Details & Contact</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="d-flex justify-content-between align-items-end mt-5 pt-4 mb-5 border-top border-color">
            <div data-aos="fade-right">
                <h2 class="font-heading fw-800 mb-2">Recently Found Items</h2>
                <p class="text-secondary m-0">Claim your item back by verifying ownership details.</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/found/search.php" class="btn btn-premium-outline d-none d-md-block" data-aos="fade-left">Browse All Found Items <i class="fa-solid fa-arrow-right ms-2"></i></a>
        </div>

        <div class="row g-4">
            <?php if (empty($latest_found)): ?>
                <div class="col-12 text-center py-5" data-aos="fade-up">
                    <div class="glass-panel p-5">
                        <i class="fa-solid fa-clipboard-check text-muted fs-1 mb-3"></i>
                        <h5 class="text-secondary">No items reported found recently.</h5>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($latest_found as $item): ?>
                    <div class="col-lg-4 col-md-6" data-aos="fade-up">
                        <div class="glass-card h-100 position-relative">
                            <span class="item-badge badge-found">Found</span>
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image'] ?: 'default-item.png'; ?>" class="w-100" style="height: 220px; object-fit: cover;" alt="<?php echo sanitize($item['title']); ?>">
                            
                            <div class="p-4">
                                <span class="badge bg-secondary text-secondary mb-2" style="font-size: 0.8rem;"><?php echo sanitize($item['category_name']); ?></span>
                                <h5 class="font-heading fw-700 mb-2"><?php echo sanitize($item['title']); ?></h5>
                                <p class="text-secondary text-truncate-2 mb-3" style="font-size: 0.9rem;"><?php echo sanitize($item['description']); ?></p>
                                
                                <div class="d-flex flex-column gap-2 mb-4" style="font-size: 0.85rem; color: var(--text-muted);">
                                    <div><i class="fa-solid fa-location-dot me-2 text-success"></i><?php echo sanitize($item['location']); ?></div>
                                    <div><i class="fa-regular fa-calendar me-2 text-success"></i>Found: <?php echo formatDate($item['found_date']); ?></div>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/found/view.php?id=<?php echo $item['id']; ?>" class="btn btn-premium w-100">View Details & Claim</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Platform Features Section -->
<section id="features" class="py-5 bg-opacity-25" style="background: var(--bg-secondary);">
    <div class="container py-4">
        <div class="text-center max-width-600 mx-auto mb-5" data-aos="fade-up">
            <h2 class="font-heading fw-800 mb-3">Enterprise lost & found features</h2>
            <p class="text-secondary">Explore advanced functionalities designed to guarantee swift resolution of lost belongings.</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6" data-aos="fade-up">
                <div class="glass-panel p-4 h-100">
                    <div class="btn btn-premium rounded p-3 mb-4"><i class="fa-solid fa-qrcode fs-4"></i></div>
                    <h5 class="font-heading fw-700 mb-3">QR Tracking System</h5>
                    <p class="text-secondary" style="font-size: 0.9rem; line-height: 1.6;">Every item reported automatically gets an assigned tracking QR code that security or finders can scan for instant verification details.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-panel p-4 h-100">
                    <div class="btn btn-premium rounded p-3 mb-4"><i class="fa-solid fa-shield-halved fs-4"></i></div>
                    <h5 class="font-heading fw-700 mb-3">Secure Verification</h5>
                    <p class="text-secondary" style="font-size: 0.9rem; line-height: 1.6;">Ownership claims require verification proof, descriptions, and admin reviews. Zero risk of wrongful claims.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-panel p-4 h-100">
                    <div class="btn btn-premium rounded p-3 mb-4"><i class="fa-solid fa-bell-concierge fs-4"></i></div>
                    <h5 class="font-heading fw-700 mb-3">Real-time Notifications</h5>
                    <p class="text-secondary" style="font-size: 0.9rem; line-height: 1.6;">Automated email simulation and system logs inform both parties on changes in claim reviews, comments, or item statuses.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQs Section -->
<section id="faq" class="py-5 my-4">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5" data-aos="fade-right">
                <h2 class="font-heading fw-800 mb-3">Frequently Asked Questions</h2>
                <p class="text-secondary">Have questions about how to register, verify, or claim reported items? Feel free to contact our support department.</p>
            </div>
            <div class="col-lg-7" data-aos="fade-left">
                <div class="accordion accordion-flush glass-panel p-3" id="faqAccordion">
                    <div class="accordion-item bg-transparent border-color py-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button bg-transparent collapsed text-primary fw-600 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faq-1">
                                Who can submit claims or report found items?
                            </button>
                        </h2>
                        <div id="faq-1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary" style="font-size: 0.9rem;">
                                Registered students and university administration staff can submit lost reports, upload found item logs, and initiate claim request reviews.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item bg-transparent border-color py-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button bg-transparent collapsed text-primary fw-600 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faq-2">
                                How do I prove that a found item belongs to me?
                            </button>
                        </h2>
                        <div id="faq-2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary" style="font-size: 0.9rem;">
                                During the claim filing process, you will be prompted to supply a detailed description (unique identifying marks, serial numbers, passwords, contents inside bags) and upload physical photo proof.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item bg-transparent border-0 py-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button bg-transparent collapsed text-primary fw-600 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faq-3">
                                What is the reward feature?
                            </button>
                        </h2>
                        <div id="faq-3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary" style="font-size: 0.9rem;">
                                Reporting lost items includes an optional reward field. The owner pays this reward directly to the finder upon successful and verified claim approval.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form (AJAX integration) -->
<section id="contact" class="py-5 bg-opacity-25" style="background: var(--bg-secondary);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="glass-panel p-4 p-md-5">
                    <h3 class="font-heading fw-800 mb-4 text-center">Have inquiries? Contact Support</h3>
                    <form id="contactForm" onsubmit="handleContactSubmit(event)">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-500">Your Name</label>
                                <input type="text" class="form-control form-premium-control" id="contactName" placeholder="Full Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-500">Your Email</label>
                                <input type="email" class="form-control form-premium-control" id="contactEmail" placeholder="name@university.edu" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary fw-500">Your Message</label>
                            <textarea class="form-control form-premium-control" id="contactMessage" rows="5" placeholder="State your question..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-premium w-100 py-3"><i class="fa-solid fa-paper-plane me-2"></i>Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function handleContactSubmit(event) {
    event.preventDefault();
    const name = document.getElementById('contactName').value;
    const email = document.getElementById('contactEmail').value;
    const message = document.getElementById('contactMessage').value;

    // Simulate AJAX contact form post
    Toast.show("Thank you " + name + "! Your support message was received.", "success");
    document.getElementById('contactForm').reset();
}
</script>

<?php
// Load common footer
require_once 'includes/footer.php';
?>
