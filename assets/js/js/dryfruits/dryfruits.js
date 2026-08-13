$(document).ready(function () {
    $(document).on("keyup", "#search", function () {
        const query = $(this).val().trim();
      
        // ✅ If empty, show all dryfruits again
        if (query.length === 0) {
          loadDryfruits(); 
          return;
        }
      
        // ✅ Optional: require 2 chars
        if (query.length < 2) {
          $("#products-dryfruits").html("<p>Type at least 2 characters to search...</p>");
          return;
        }
      
        $.ajax({
          url: "assets/db_query/dryfruits/dryfruits_query.php",
          method: "GET",
          dataType: "json",
          cache: false,
          data: { action: "product_search_dryfruits", query: query },
          success: function (res) {
            if (res.status !== "success") {
              $("#products-dryfruits").html("<p>No products found</p>");
              return;
            }
            renderProducts(res.data, "#products-dryfruits");
          },
          error: function (xhr) {
            console.log(xhr.status, xhr.responseText);
            $("#products-dryfruits").html("<p>Error loading products</p>");
          }
        });
      });
      
      // ✅ Load all dryfruits products
      function loadDryfruits() {
        $.ajax({
          url: "assets/db_query/dryfruits/dryfruits_query.php",
          method: "GET",
          dataType: "json",
          cache: false,
          data: { action: "dryfruits_products" },
          success: function (res) {
            if (res.status !== "success") {
              $("#products-dryfruits").html("<p>No products found</p>");
              return;
            }
            renderProducts(res.data, "#products-dryfruits");
          },
          error: function (xhr) {
            console.log(xhr.status, xhr.responseText);
            $("#products-dryfruits").html("<p>Error loading products</p>");
          }
        });
      }
      
      // ✅ Single renderer used for both search + all products
      function renderProducts(products, containerId) {
        let html = "";
      
        products.forEach(function (product) {
          let image = product.image ? "assets/uploads/" + product.image : "images/default.jpg";
      
          let discount = "";
          if (product.dis_price && product.price) {
            let percent = Math.round((1 - product.dis_price / product.price) * 100);
            discount = `<span class="status">${percent}%</span>`;
          }
      
          html += `
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="product">
                <a href="productdetail.php?product=${slugify(product.product_name)}" class="img-prod">
                  <img class="img-fluid" src="${image}" alt="${product.product_name}">
                  ${discount}
                  <div class="overlay"></div>
                </a>
      
                <div class="text py-3 pb-4 px-3 text-center">
                  <h3>
                    <a href="productdetail.php?product=${slugify(product.product_name)}">
                      ${product.product_name} (${product.quantity})
                    </a>
                  </h3>
      
                  <div class="d-flex justify-content-center">
                    <div class="pricing">
                      <p class="price">
                        ${product.dis_price && product.price
                          ? `<span class="mr-2 price-dc">&#8377;${product.price}</span>
                             <span class="price-sale">&#8377;${product.dis_price}</span>`
                          : `<span>&#8377;${product.price}</span>`}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;
        });
      
        $(containerId).html(html);
      }
      
    fetchDryfruitsProducts();
   
});
function fetchDryfruitsProducts() {
    $.ajax({
        url: 'assets/db_query/dryfruits/dryfruits_query.php?action=dryfruits_products',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                let html = '';
                res.data.forEach(function (product) {
                    let slug = product.product_name.toLowerCase().replace(/\s+/g, '-');
                    let image = product.image ? 'assets/uploads/' + product.image : 'images/default.jpg';

                    let discount = '';
                    if (product.dis_price && product.price) {
                        let percent = Math.round((1 - product.dis_price / product.price) * 100);
                        discount = `<span class="status">${percent}%</span>`;
                    }

                    html += `
                        <div class="col-md-6 col-lg-3 mb-5">
                            <div class="product">
                                <a href="productdetail.php?product=${slugify(product.product_name)}" class="img-prod">
                                    <img class="img-fluid" src="${image}" alt="${product.product_name}">
                                    ${discount}
                                    <div class="overlay"></div>
                                </a>
                                <div class="text py-3 pb-4 px-3 text-center">
                                    <h3><a href="productdetail.php?product=${slugify(product.product_name)}">${product.product_name} (${product.quantity})</a></h3>
                                    <div class="d-flex">
                                        <div class="pricing">
                                            <p class="price">`;

                    if (product.dis_price && product.price) {
                        html += `<span class="mr-2 price-dc">&#8377;${product.price}</span>
                                 <span class="price-sale">&#8377;${product.dis_price}</span>`;
                    } else {
                        html += `<span>&#8377;${product.price}</span>`;
                    }

                    html += `</p>
                                        </div>
                                    </div>
                                    <div class="bottom-area d-flex px-3">
                                        <div class="m-auto d-flex">
                                        <a href="productdetail.php?product=${slugify(product.product_name)}" 
                                        class="add-to-cart d-flex justify-content-center align-items-center text-center product-detail-btn" 
                                        data-id="${product.id}">
                                            <span><ion-icon name="menu"></ion-icon></span>
                                        </a>
                                        <a href="#" class="buy now d-flex justify-content-center align-items-center btn btn-primary add-to-cart" id="add-to-cart" data-id="${product.id}"
                                            <span><ion-icon name="cart"></ion-icon></a></span>
                                        </a>
                                        <a href="#" class="heart d-flex justify-content-center align-items-center wishlist-btn" 
                                          data-product-id="${product.id}">
                                           <span><ion-icon name="heart"></ion-icon></span>
                                        </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                });

                $('#products-dryfruits').html(html);
            } else {
                $('#products-dryfruits').html('<p>No dryfruits products found.</p>');
            }
        },
        error: function () {
            $('#products-dryfruits').html('<p>Error loading products.</p>');
        }
    });
}
