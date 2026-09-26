const fs = require('fs');

let content = fs.readFileSync('index.php', 'utf8');

// 1. Remove Our Collections
const colRegex = /<!-- =+[\s\S]+?2\. OUR COLLECTIONS \(Redesigned\)[\s\S]+?<\/section>/;
content = content.replace(colRegex, '');

// 2. Rename Best Products to Best Sellers
content = content.replace('4. BEST PRODUCTS (Dynamic from Database)', '4. BEST SELLERS (Dynamic from Database)');
content = content.replace('<h2 class="v-section-title">BEST PRODUCTS</h2>', '<h2 class="v-section-title" style="font-family:var(--v-font-serif); color:var(--v-forest-deep);">BEST SELLERS</h2>');

// 3. Create the layout for Healthy Choices and Traditional Foods
const healthyChoicesHTML = `
    <!-- ====================================================================
         HEALTHY CHOICES
         ==================================================================== -->
    <section class="v-products-section" id="healthy-choices-section" style="background:#fcfaf5; padding:60px 0; border-top:1px solid #f0eadd;">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:var(--v-forest);">NUTRITION FIRST</span>
                    <h2 class="v-section-title" style="color:var(--v-forest-deep); font-family:var(--v-font-serif);">HEALTHY CHOICES</h2>
                    <p class="v-section-subtitle text-left">Nourishing selections to support your everyday wellness journey.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="shop.php" class="v-btn-tertiary" style="color:var(--v-forest); font-weight:600;">View All Healthy Choices &rarr;</a>
                </div>
            </div>
            <div class="row" id="healthy-choices-container">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         TRADITIONAL FOODS
         ==================================================================== -->
    <section class="v-products-section" id="traditional-foods-section" style="background:#f9f5ed; padding:60px 0; border-top:1px solid #e8e2d2;">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:#8b5a2b;">HERITAGE TASTE</span>
                    <h2 class="v-section-title" style="color:#5c3a21; font-family:var(--v-font-serif);">TRADITIONAL FOODS</h2>
                    <p class="v-section-subtitle text-left">Authentic flavors rooted in generations of culinary wisdom.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="shop.php" class="v-btn-tertiary" style="color:#8b5a2b; font-weight:600;">View All Traditional Foods &rarr;</a>
                </div>
            </div>
            <div class="row" id="traditional-foods-container">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </section>
`;

// Insert after Why Valluvam section
const whyValluvamRegex = /(<!-- =+[\s\S]+?9\. WHY VALLUVAM \(Brand Story & Pillars\)[\s\S]+?<\/section>)/;
content = content.replace(whyValluvamRegex, '$1' + healthyChoicesHTML);


// 4. Create Gifting and Everyday Essentials
const giftingAndEverydayHTML = `
    <!-- ====================================================================
         GIFTING
         ==================================================================== -->
    <section class="v-products-section" id="gifting-section" style="background:#fffaf0; padding:60px 0; border-top:1px solid #f2ead3;">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:#c0392b;">SHARE THE GOODNESS</span>
                    <h2 class="v-section-title" style="color:#7a1f16; font-family:var(--v-font-serif);">GIFTING</h2>
                    <p class="v-section-subtitle text-left">Curated boxes and festive bundles for your loved ones.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="combo.php" class="v-btn-tertiary" style="color:#c0392b; font-weight:600;">View All Gifting &rarr;</a>
                </div>
            </div>
            <div class="row" id="gifting-container">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         EVERYDAY ESSENTIALS
         ==================================================================== -->
    <section class="v-products-section" id="everyday-essentials-section" style="background:#ffffff; padding:60px 0; border-top:1px solid #eef0ec;">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div class="v-section-header text-left mb-0">
                    <span class="v-eyebrow" style="color:#4a6050;">DAILY PANTRY</span>
                    <h2 class="v-section-title" style="color:#1a3d2b; font-family:var(--v-font-serif);">EVERYDAY ESSENTIALS</h2>
                    <p class="v-section-subtitle text-left">Pure and unadulterated staples for your daily cooking needs.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="shop.php" class="v-btn-tertiary" style="color:#1a3d2b; font-weight:600;">View All Essentials &rarr;</a>
                </div>
            </div>
            <div class="row" id="everyday-essentials-container">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </section>
`;

// Remove Shop By Lifestyle (Section 13) and insert Gifting + Everyday Essentials after Did You Know
const didYouKnowAndLifestyleRegex = /(<!-- =+[\s\S]+?12\. DID YOU KNOW\? \(Premium Redesign\)[\s\S]+?<\/section>)[\s\S]*?(<!-- =+[\s\S]+?13\. SHOP BY LIFESTYLE \(Premium Redesign\)[\s\S]+?<\/section>)/;
content = content.replace(didYouKnowAndLifestyleRegex, '$1' + giftingAndEverydayHTML);

fs.writeFileSync('index.php', content, 'utf8');
console.log('Homepage successfully updated.');
