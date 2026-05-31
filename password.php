<?php

// 1. Protect against SQL injection (use with ALL database queries)
function safeQuery($conn, $sql, $types, ...$params) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// 2. Protect against XSS (use with ALL output)
function escape($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// 3. Protect against both - Complete example
function getPatientById($conn, $id) {
    // SQL protection
    $stmt = mysqli_prepare($conn, "SELECT * FROM patients WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $patient = mysqli_fetch_assoc($result);
    
    // XSS protection
    if ($patient) {
        $patient['name'] = escape($patient['name']);
        $patient['email'] = escape($patient['email']);
    }
    
    return $patient;
}
?>