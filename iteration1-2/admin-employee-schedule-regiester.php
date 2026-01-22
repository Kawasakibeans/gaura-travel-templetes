<?php
/**
 * Template Name: Admin Lock/Unlock View
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

// Database configuration
$host = 'localhost';
$db = 'gaurat_gauratravel';
$user = 'gaurat_sriharan';
$pass = 'r)?2lc^Q0cAE';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    die("DB connection error: " . $e->getMessage());
}

// Table name
$availability_table = 'wpk4_backend_employee_schedule';

// Initialize message variable
$msg = '';

// Admin Check
if (current_user_can('administrator')) {
    // Handle lock/unlock action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_status']) && isset($_POST['lock_nonce']) && wp_verify_nonce($_POST['lock_nonce'], 'lock_unlock_action')) {
        $lock_status = $_POST['lock_status']; // 0 for unlock, 1 for lock
        
        try {
            // Update the lock status in the database
            $stmt = $pdo->prepare("UPDATE `$availability_table` SET is_locked = ? WHERE 1");
            $stmt->execute([$lock_status]);

            $msg = $lock_status ? ['type' => 'success', 'text' => 'Availability Register Locked!'] : 
                                 ['type' => 'success', 'text' => 'Availability Register Unlocked!'];
        } catch (Exception $e) {
            $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
        }
    }
} else {
    // If not an admin, redirect to home or show an error
    wp_redirect(home_url());
    exit;
}

get_header(); // load WordPress header
?>

<!-- Admin Lock/Unlock Page Content -->
<div class="container mt-5 pt-4">
    <?php if (!empty($msg)): ?>
        <div class="floating-notification">
            <div class="alert alert-<?= htmlspecialchars($msg['type']) ?> alert-dismissible fade show shadow" role="alert">
                <?php if ($msg['type'] === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle me-2"></i>
                <?php endif; ?>
                <?= htmlspecialchars($msg['text']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="text-center mb-4">Admin Lock/Unlock Availability Register</h2>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php
            // Get the current lock status from the database
            $lock_status = $pdo->query("SELECT is_locked FROM `$availability_table` LIMIT 1")->fetchColumn();
            $lock_text = $lock_status ? 'Unlock' : 'Lock';
            ?>
            
            <!-- Lock/Unlock Form for Admin -->
            <form method="post" class="text-center">
                <?php wp_nonce_field('lock_unlock_action', 'lock_nonce'); ?>
                <input type="hidden" name="lock_status" value="<?= $lock_status ? '0' : '1' ?>">
                <button type="submit" class="btn btn-warning btn-lg"><?= $lock_text ?> Availability</button>
            </form>

            <p class="text-center mt-3">
                <a href="<?= home_url(); ?>" class="btn btn-info">Back to Dashboard</a>
            </p>
        </div>
    </div>
</div>

<?php get_footer(); // load WordPress footer ?>
