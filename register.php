<?php
session_start();

// 🔧 PERBAIKAN 1: INCLUDE KONEKSI DENGAN BENAR
require_once 'koneksi.php'; // Pastikan file koneksi.php sudah sesuai dengan solusi sebelumnya
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
        }
        
        .register-card {
            border-radius: 20px;
            border: none;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .brand-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--success-color), #27ae60);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .brand-logo i {
            font-size: 2rem;
            color: white;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--success-color);
            box-shadow: 0 0 0 0.25rem rgba(46, 204, 113, 0.25);
        }
        
        .input-group-text {
            background-color: white;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--success-color), #27ae60);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.4);
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
        }
        
        .requirement-list {
            font-size: 0.875rem;
        }
        
        .requirement-list i {
            width: 20px;
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
        }
        
        .badge-admin {
            background-color: var(--danger-color);
            color: white;
        }
        
        .badge-pemilik {
            background-color: var(--warning-color);
            color: white;
        }
    </style>
</head>
<body class="gradient-bg">
    <div class="container">
        <div class="row min-vh-100 align-items-center justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card register-card shadow-lg">
                    <div class="card-body p-5">
                        <!-- Logo Brand -->
                        <div class="brand-logo">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        
                        <h2 class="text-center mb-4 fw-bold text-dark">Buat Akun</h2>
                        <p class="text-center text-muted mb-4">Bergabunglah dengan kami hari ini dan mulai gunakan layanan</p>

                        <!-- PHP Processing -->
                        <?php
                        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                            // 🔧 PERBAIKAN 2: GUNAKAN $koneksi BUKAN $conn
                            // require_once 'koneksi.php'; // Sudah diinclude di atas

                            // 🔧 PERBAIKAN 3: GUNAKAN PREPARED STATEMENT UNTUK KEAMANAN
                            $username = $_POST['username'];
                            $password = $_POST['password'];
                            $confirm_password = $_POST['confirm_password'];
                            $role = $_POST['role']; // Tambahan: Ambil role dari form

                            if (empty($username) || empty($password) || empty($confirm_password) || empty($role)) {
                                echo '<div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        Semua field harus diisi
                                      </div>';
                            } elseif ($password != $confirm_password) {
                                echo '<div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        Password dan konfirmasi password tidak sama
                                      </div>';
                            } elseif (strlen($username) < 3) {
                                echo '<div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        Username minimal 3 karakter
                                      </div>';
                            } elseif (strlen($password) < 6) {
                                echo '<div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        Password minimal 6 karakter
                                      </div>';
                            } elseif (!in_array($role, ['admin', 'pemilik'])) {
                                echo '<div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        Role tidak valid
                                      </div>';
                            } else {
                                try {
                                    // 🔧 PERBAIKAN 4: PREPARED STATEMENT UNTUK CEK USERNAME
                                    $check_query = "SELECT * FROM users WHERE username = ?";
                                    $check_stmt = $koneksi->prepare($check_query);
                                    $check_stmt->bind_param("s", $username);
                                    $check_stmt->execute();
                                    $check_result = $check_stmt->get_result();
                                    
                                    if ($check_result->num_rows > 0) {
                                        echo '<div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                Username sudah digunakan
                                              </div>';
                                    } else {
                                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                        
                                        // 🔧 PERBAIKAN 5: PREPARED STATEMENT UNTUK INSERT DENGAN ROLE
                                        $insert_query = "INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())";
                                        $insert_stmt = $koneksi->prepare($insert_query);
                                        $insert_stmt->bind_param("sss", $username, $hashed_password, $role);
                                        
                                        if ($insert_stmt->execute()) {
                                            $insert_stmt->close();
                                            $check_stmt->close();
                                            
                                            // 🔧 PERBAIKAN 6: TIDAK PERLU mysqli_close() KARENA OOP
                                            header('Location: login.php?message=Registrasi+berhasil.+Silakan+login.');
                                            exit();
                                        } else {
                                            echo '<div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
                                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                    Error: ' . $insert_stmt->error . '
                                                  </div>';
                                        }
                                        $insert_stmt->close();
                                    }
                                    $check_stmt->close();
                                    
                                } catch (Exception $e) {
                                    echo '<div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                            Terjadi kesalahan sistem: ' . $e->getMessage() . '
                                          </div>';
                                }
                            }
                            // 🔧 PERBAIKAN 7: TIDAK PERLU mysqli_close() KARENA OOP
                        }
                        ?>

                        <!-- Form Register -->
                        <form method="POST" autocomplete="off">
                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input id="username" name="username" type="text" required 
                                           class="form-control" maxlength="50" 
                                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                           placeholder="Pilih username">
                                </div>
                                <small class="text-muted">Minimal 3 karakter</small>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label fw-semibold">Role</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person-badge"></i>
                                    </span>
                                    <select id="role" name="role" class="form-select" required>
                                        <option value="">Pilih Role</option>
                                        <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>
                                            Admin 
                                            <span class="badge badge-admin role-badge ms-2">Akses Penuh</span>
                                        </option>
                                        <option value="pemilik" <?= (isset($_POST['role']) && $_POST['role'] == 'pemilik') ? 'selected' : '' ?>>
                                            Pemilik 
                                            <span class="badge badge-pemilik role-badge ms-2">Akses Laporan</span>
                                        </option>
                                    </select>
                                </div>
                                <small class="text-muted">
                                    <span class="badge badge-admin role-badge">Admin</span> dapat mengakses semua fitur
                                    <br>
                                    <span class="badge badge-pemilik role-badge">Pemilik</span> hanya dapat mengakses laporan
                                </small>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input id="password" name="password" type="password" required 
                                           class="form-control" maxlength="255" 
                                           placeholder="Buat password">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">Konfirmasi Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input id="confirm_password" name="confirm_password" type="password" required 
                                           class="form-control" maxlength="255" 
                                           placeholder="Konfirmasi password Anda">
                                </div>
                            </div>

                            <!-- Persyaratan -->
                            <div class="mb-4">
                                <small class="fw-semibold d-block mb-2">Persyaratan:</small>
                                <div class="requirement-list">
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <small class="ms-2">Username minimal 3 karakter</small>
                                    </div>
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <small class="ms-2">Password minimal 6 karakter</small>
                                    </div>
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <small class="ms-2">Password harus sama dengan konfirmasi</small>
                                    </div>
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <small class="ms-2">Pilih role sesuai kebutuhan</small>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-register text-white w-100 py-3 mb-4">
                                <i class="bi bi-person-plus me-2"></i>Buat Akun
                            </button>
                        </form>

                        <!-- Link Login -->
                        <div class="text-center">
                            <p class="text-muted mb-0">Sudah punya akun?</p>
                            <a href="index.php" class="fw-bold text-decoration-none" 
                               style="color: var(--success-color);">Masuk di sini</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>