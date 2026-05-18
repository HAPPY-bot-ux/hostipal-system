<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$specialization = isset($_GET['specialization']) ? $_GET['specialization'] : '';

$query = "SELECT d.id, u.full_name as name, d.specialization 
          FROM doctors d
          JOIN users u ON d.user_id = u.id
          WHERE d.specialization LIKE :spec AND u.is_active = 1";
$stmt = $db->prepare($query);
$stmt->bindValue(':spec', "%$specialization%");
$stmt->execute();

$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($doctors);
?>