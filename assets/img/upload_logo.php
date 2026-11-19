<?php
/**
 * One-time logo uploader — delete this file after use.
 * Visit: http://localhost/PMAS/assets/img/upload_logo.php
 */
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    $file = $_FILES['logo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','gif','webp'])) {
        $msg = '<span style="color:red">Only PNG, JPG, GIF, WEBP allowed.</span>';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = '<span style="color:red">Upload error: ' . $file['error'] . '</span>';
    } else {
        $dest = __DIR__ . '/pma_logo.png';
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $msg = '<span style="color:green">✓ Logo saved as pma_logo.png — you can now delete this file.</span>';
        } else {
            $msg = '<span style="color:red">Failed to move file. Check folder permissions.</span>';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload PMA Logo</title>
    <style>
        body { font-family: sans-serif; max-width: 500px; margin: 60px auto; padding: 20px; }
        input[type=file] { margin: 10px 0; }
        button { background: #C0001A; color: white; border: none; padding: 10px 24px; border-radius: 5px; cursor: pointer; font-size: 14px; }
        img { max-width: 160px; border-radius: 50%; border: 3px solid gold; display: block; margin: 10px 0; }
    </style>
</head>
<body>
    <h2>Upload PMA Logo</h2>
    <?php if ($msg): ?><p><?= $msg ?></p><?php endif; ?>
    <?php if (file_exists(__DIR__ . '/pma_logo.png')): ?>
    <p><strong>Current logo:</strong></p>
    <img src="pma_logo.png?t=<?= time() ?>" alt="PMA Logo">
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Select logo image (PNG recommended):</label><br>
        <input type="file" name="logo" accept="image/*" required>
        <br><button type="submit">Upload Logo</button>
    </form>
    <p style="font-size:12px;color:#888;margin-top:20px;">
        ⚠️ Delete this file after uploading the logo.
    </p>
</body>
</html>
