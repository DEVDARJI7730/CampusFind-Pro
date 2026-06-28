<?php
/**
 * CampusFind Pro - Common Footer Layout
 */
?>

<!-- Premium Footer Section -->
<footer class="mt-auto py-5 border-top border-glass" style="background: var(--bg-card); backdrop-filter: blur(10px);">
    <div class="container">
        <div class="row g-4 justify-content-between">
            <!-- Brand Info Column -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up">
                <a class="navbar-brand d-flex align-items-center gap-2 mb-3" href="<?php echo SITE_URL; ?>/index.php">
                    <i class="fa-solid fa-compass-drafting fs-3 text-primary"></i>
                    <span class="text-primary font-heading fw-800">CampusFind <span class="fw-400 text-secondary" style="font-size: 0.95rem;">Pro</span></span>
                </a>
                <p class="text-secondary mb-4" style="font-size: 0.9rem; line-height: 1.6;">
                    The premium Lost & Found Management Software designed for academic institutions to connect students, streamline claim processing, and safeguard campus belongings.
                </p>
                <div class="d-flex gap-3">
                    <a href="https://x.com/DevDarji80404" class="btn btn-sm btn-outline-secondary rounded-circle" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                    <a href="https://github.com/DEVDARJI7730" class="btn btn-sm btn-outline-secondary rounded-circle" aria-label="GitHub"><i class="fa-brands fa-github"></i></a>
                    <a href="https://www.linkedin.com/in/dev-darji-65836b318/" class="btn btn-sm btn-outline-secondary rounded-circle" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
            </div>

            <!-- Fast Navigation Links -->
            <div class="col-lg-2 col-md-6 col-6" data-aos="fade-up" data-aos-delay="100">
                <h6 class="font-heading fw-700 text-uppercase text-secondary mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Platform</h6>
                <ul class="list-unstyled d-flex flex-column gap-2" style="font-size: 0.9rem;">
                    <li><a href="<?php echo SITE_URL; ?>/lost/search.php" class="text-secondary">Search Lost Items</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/found/search.php" class="text-secondary">Search Found Items</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/index.php#features" class="text-secondary">Core Features</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/index.php#faq" class="text-secondary">FAQs</a></li>
                </ul>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-2 col-md-6 col-6" data-aos="fade-up" data-aos-delay="200">
                <h6 class="font-heading fw-700 text-uppercase text-secondary mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Support & Contact</h6>
                <ul class="list-unstyled d-flex flex-column gap-2" style="font-size: 0.9rem;">
                    <li><a href="<?php echo SITE_URL; ?>/index.php#contact" class="text-secondary">Get in Touch</a></li>
                    <li><a href="#" class="text-secondary">Privacy Policy</a></li>
                    <li><a href="#" class="text-secondary">Terms of Service</a></li>
                    <li><a href="#" class="text-secondary">SaaS License</a></li>
                </ul>
            </div>

            <!-- Contact/Address Box -->
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <h6 class="font-heading fw-700 text-uppercase text-secondary mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Campus Office</h6>
                <p class="text-secondary mb-2" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-map-location-dot me-2 text-primary"></i> Gls University, New Building
                </p>
                <p class="text-secondary mb-2" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-envelope me-2 text-primary"></i> darjidev2504@gmail.com
                </p>
                <p class="text-secondary" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-phone me-2 text-primary"></i> +91 9510473018
                </p>
            </div>
        </div>

        <hr class="my-4 border-color">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <span class="text-muted" style="font-size: 0.85rem;">
                &copy; <?php echo date('Y'); ?> CampusFind Pro. All rights reserved.
            </span>
            <span class="text-muted" style="font-size: 0.85rem;">
                Crafted with <i class="fa-solid fa-heart text-danger"></i> for secure campuses.
            </span>
        </div>
    </div>
</footer>

<!-- JS Script CDNs -->
<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<!-- AOS Scroll Animations JS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- client-side QRCode library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<!-- Application Core JS -->
<script src="<?php echo SITE_URL; ?>/assets/js/app.js"></script>

<?php if (isset($extra_js)): echo $extra_js; endif; ?>

</body>
</html>
