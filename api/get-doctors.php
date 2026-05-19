<?php
// api/get-doctors.php - Get doctors by specialization
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$specialization = isset($_GET['specialization']) ? $_GET['specialization'] : '';

try {
    if ($specialization) {
        $query = "SELECT d.id, u.full_name as name, d.specialization, 
                         d.experience_years as experience, d.consultation_fee as fee,
                         d.qualification, d.available_time_start, d.available_time_end, d.available_days
                  FROM doctors d
                  JOIN users u ON d.user_id = u.id
                  WHERE d.specialization = :specialization AND u.is_active = 1
                  ORDER BY u.full_name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':specialization', $specialization);
        $stmt->execute();
        
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the response
        $result = [];
        foreach ($doctors as $doctor) {
            $result[] = [
                'id' => $doctor['id'],
                'name' => $doctor['name'],
                'specialization' => $doctor['specialization'],
                'experience' => $doctor['experience'],
                'fee' => $doctor['fee'],
                'qualification' => $doctor['qualification'],
                'start_time' => $doctor['available_time_start'],
                'end_time' => $doctor['available_time_end'],
                'available_days' => $doctor['available_days']
            ];
        }
        
        echo json_encode($result);
    } else {
        // Return all doctors if no specialization specified
        $query = "SELECT d.id, u.full_name as name, d.specialization, 
                         d.experience_years as experience, d.consultation_fee as fee,
                         d.qualification, d.available_time_start, d.available_time_end, d.available_days
                  FROM doctors d
                  JOIN users u ON d.user_id = u.id
                  WHERE u.is_active = 1
                  ORDER BY d.specialization, u.full_name";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($doctors as $doctor) {
            $result[] = [
                'id' => $doctor['id'],
                'name' => $doctor['name'],
                'specialization' => $doctor['specialization'],
                'experience' => $doctor['experience'],
                'fee' => $doctor['fee'],
                'qualification' => $doctor['qualification'],
                'start_time' => $doctor['available_time_start'],
                'end_time' => $doctor['available_time_end'],
                'available_days' => $doctor['available_days']
            ];
        }
        
        echo json_encode($result);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>