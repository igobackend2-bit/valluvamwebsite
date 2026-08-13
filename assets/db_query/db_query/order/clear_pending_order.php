<?php
session_start();
// Clear pending order from session if payment is cancelled
unset($_SESSION['pending_order']);
echo json_encode(['status' => 'success']);

