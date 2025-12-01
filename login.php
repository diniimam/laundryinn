<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
        }
        
        .login-card {
            border-radius: 20px;
            border: none;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .brand-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
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
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .input-group-text {
            background-color: white;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
        }
        
        .feature-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .feature-icon i {
            color: white;
            font-size: 1.2rem;
        }
    </style>
</head>
<body class="gradient-bg">
    <div class="container">
        <div class="row min-vh-100 align-items-center justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card login-card shadow-lg">
                    <div class="card-body p-5">
                        <!-- Logo Brand -->
                        <div class="brand-logo">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        
                        <h2 class="text-center mb-4 fw-bold text-dark">Selamat Datang</h2>
                        <p class="text-center text-muted mb-4">Masuk ke akun Anda untuk melanjutkan</p>

                        <!-- Pesan Alert -->
                        <?php if (!empty($_GET['error'])): ?>
                            <div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= htmlspecialchars($_GET['error']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($_GET['message'])): ?>
                            <div class="alert alert-success alert-custom d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?= htmlspecialchars($_GET['message']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Form Login -->
                        <form method="post" action="proses_login.php" autocomplete="off">
                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input id="username" name="username" type="text" required 
                                           class="form-control" maxlength="100" autocomplete="off"
                                           placeholder="Masukkan username Anda">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input id="password" name="password" type="password" required 
                                           class="form-control" maxlength="255" autocomplete="off"
                                           placeholder="Masukkan password Anda">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-login text-white w-100 py-3 mb-4">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
                            </button>
                        </form>



                        <!-- Link Register -->
                        <div class="text-center">
                            <p class="text-muted mb-0">Belum punya akun?</p>
                            <a href="register.php" class="fw-bold text-decoration-none" 
                               style="color: var(--primary-color);">Daftar di sini</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Reset form ketika halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('form').reset();
        });
    </script>
</body>
</html>