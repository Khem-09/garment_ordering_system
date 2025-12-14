<?php
session_start();
require_once "../classes/admin.php"; 

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['garment_id'])) {
    $garment_id = $_POST['garment_id'];
    $admin = new Admin();

    try {
        $image_url_to_delete = $admin->deleteGarment($garment_id); 

        if ($image_url_to_delete !== false) {
            if (!empty($image_url_to_delete)) {
                 $absolute_image_path = realpath(__DIR__ . '/../' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $image_url_to_delete), DIRECTORY_SEPARATOR));

                if ($absolute_image_path && file_exists($absolute_image_path)) {
                    if (!unlink($absolute_image_path)) {
                        error_log("Failed to delete image file: " . $absolute_image_path . " for deleted garment ID: " . $garment_id);
                        $_SESSION['error'] = "Garment marked as deleted, but failed to remove the associated image file: " . basename($image_url_to_delete);
                    } else {
                        $_SESSION['message'] = "Garment (ID: {$garment_id}) and its image deleted successfully.";
                    }
                } else {
                     $_SESSION['message'] = "Garment (ID: {$garment_id}) marked as deleted. Associated image file not found or path incorrect.";
                     error_log("Image file not found for deleted garment ID {$garment_id} at path: " . $image_url_to_delete);
                }
            } else {
                 $_SESSION['message'] = "Garment (ID: {$garment_id}) marked as deleted successfully (no image file associated).";
            }
        } else {
            $_SESSION['error'] = "Failed to mark garment (ID: {$garment_id}) as deleted. Check database or logs.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred during garment deletion: " . $e->getMessage();
        error_log("Error during garment deletion (ID: {$garment_id}): " . $e->getMessage());
    }
} else {
    $_SESSION['error'] = "Invalid request method or missing garment ID.";
}

header("Location: adminpage.php?page=manageGarments");
exit();
?>