<?php
$product = [
  'product_name' => '',
  'price' => '',
  'dis_price' => '',
  'description' => '',
  'category' => '',
  'quantity' => '',
  'image' => '',
  'benefits' => '',
  'rating' => ''
];

$isEdit = false;

if (isset($_GET['id'])) {
  $isEdit = true;
  $id = $_GET['id'];

  try {
    $pdo = new PDO("mysql:host=localhost;valluvam=valluam", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM product_details WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
      $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
      echo "<script>alert('Product not found'); window.location.href='product_list.php';</script>";
      exit;
    }

  } catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $dis_price = $_POST['dis_price'];
    $desc = $_POST['description'];
    $cat = $_POST['category'];
    $qty = $_POST['quantity'];
    $benefits = $_POST['benefits'];
    $rating = $_POST['rating'];

    if ($isEdit) {
      $stmt = $pdo->prepare("UPDATE products SET product_name=?, price=?, dis_price=?, description=?, category=?, quantity=?, benefits=?, rating=? WHERE id=?");
      $stmt->execute([$name, $price, $dis_price, $desc, $cat, $qty, $benefits, $rating, $id]);
      echo "<script>alert('Product updated'); window.location.href='product_list.php';</script>";
    } else {
      $stmt = $pdo->prepare("INSERT INTO products (product_name, price, dis_price, description, category, quantity, benefits, rating) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([$name, $price, $dis_price, $desc, $cat, $qty, $benefits, $rating]);
      echo "<script>alert('Product added'); window.location.href='product_list.php';</script>";
    }
  } catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
      $name = $_POST['name'];
      $price = $_POST['price'];
      $dis_price = $_POST['dis_price'];
      $desc = $_POST['description'];
      $cat = $_POST['category'];
      $qty = $_POST['quantity'];
      $benefits = $_POST['benefits'];
      $rating = $_POST['rating'];
  
      if ($isEdit) {
        $stmt = $pdo->prepare("UPDATE products SET product_name=?, price=?, dis_price=?, description=?, category=?, quantity=?, benefits=?, rating=? WHERE id=?");
        $stmt->execute([$name, $price, $dis_price, $desc, $cat, $qty, $benefits, $rating, $id]);
        echo "<script>alert('Product updated'); window.location.href='product_list.php';</script>";
      } else {
        $stmt = $pdo->prepare("INSERT INTO products (product_name, price, dis_price, description, category, quantity, benefits, rating) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $dis_price, $desc, $cat, $qty, $benefits, $rating]);
        echo "<script>alert('Product added'); window.location.href='product_list.php';</script>";
      }
    } catch (PDOException $e) {
      echo "DB Error: " . $e->getMessage();
    }
  }
  
?>
