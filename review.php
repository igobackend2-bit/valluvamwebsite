<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Valluvam</title>
  <style>
    /* ===== Homepage customer reviews (static grid) =====
       Was a single-card GSAP slider; converted to a responsive grid so all
       real reviews are visible at once without a JS animation dependency. */
    .reviews-section {
      width: 100%;
    }

    .reviews-section .blue-line {
      height: 0.3rem;
      width: 6rem;
      background-color: #82ae46;
      margin: 0 auto 2.5rem;
    }

    .review-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 24px;
    }

    @media (max-width: 991.98px) {
      .review-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 575.98px) {
      .review-grid {
        grid-template-columns: 1fr;
      }
    }

    .review-card {
      background: #fff;
      border: 1px solid #eee;
      border-radius: 10px;
      padding: 28px 22px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(99, 99, 99, 0.1);
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .review-card .avatar {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background-size: cover;
      background-position: center;
      box-shadow: 3px -2px #82ae46;
      margin-bottom: 14px;
    }

    .review-card .name {
      font-weight: bold;
      font-size: 16px;
      letter-spacing: .03rem;
      margin-bottom: 2px;
    }

    .review-card .role {
      font-size: 13px;
      color: #82ae46;
      text-transform: uppercase;
      letter-spacing: .05rem;
      margin-bottom: 12px;
    }

    .review-card .quote {
      font-size: 14px;
      line-height: 1.8;
      color: #6b6b6b;
    }
  </style>
</head>
<body>
	<section class="ftco-section reviews-section">
		<div class="container">
			<div class="row justify-content-center text-center">
				<div class="col-md-10 col-lg-8">
					<div class="header-section">
						<h2 class="title">Our <span style="color: #82ae46;">Reviews</span></h2>
						<p class="description">What customers say about Valluvam's nuts, dry fruits, oils, spices and millets.</p>
					</div>
				</div>
			</div>
			<div class="blue-line"></div>
			<div class="review-grid">
				<div class="review-card">
					<div class="avatar" style="background-image:url('images/customer-1.jpeg');"></div>
					<div class="name">Nirmal</div>
					<div class="role">Customer</div>
					<p class="quote">"Best place for pure cold-pressed coconut oil and fresh nuts! Quick delivery and friendly service."</p>
				</div>
				<div class="review-card">
					<div class="avatar" style="background-image:url('images/customer-2.jpeg');"></div>
					<div class="name">Pari</div>
					<div class="role">Customer</div>
					<p class="quote">"Pure sesame and groundnut oil with amazing aroma! Wholesale rates are the best. Highly satisfied."</p>
				</div>
				<div class="review-card">
					<div class="avatar" style="background-image:url('images/customer-3.jpeg');"></div>
					<div class="name">Santhosh</div>
					<div class="role">Customer</div>
					<p class="quote">"Best quality sesame oil and dry fruits! Reliable delivery and good wholesale pricing."</p>
				</div>
				<div class="review-card">
					<div class="avatar" style="background-image:url('images/customer-4.jpeg');"></div>
					<div class="name">Sanjay</div>
					<div class="role">Customer</div>
					<p class="quote">"A one-stop shop for millets, nuts, and cold-pressed oils. Great customer service with quick delivery."</p>
				</div>
			</div>
		</div>
	</section>
</body>
</html>
