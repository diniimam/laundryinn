<!-- Navbar YANG SUDAH DIPERBAIKI - DASHBOARD ACTIVE -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="dashboard.php">
        <i class="fas fa-tshirt me-2"></i>LaundryIn
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <!-- DASHBOARD - YANG INI YANG ACTIVE -->
        <li class="nav-item">
          <a class="nav-link active" href="dashboard.php">
            <i class="fas fa-home me-1"></i>Dashboard
          </a>
        </li>
        
        <!-- DROPDOWN TRANSAKSI - TANPA ACTIVE -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
             data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-exchange-alt me-1"></i>Transaksi
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
            <li>
              <a class="dropdown-item" href="transaksi.php">
                <i class="fas fa-plus-circle me-2 text-success"></i>Baju Masuk
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="daftar_transaksi.php">
                <i class="fas fa-list me-2 text-primary"></i>Pengembalian 
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="transaksi_pending.php">
                <i class="fas fa-clock me-2 text-warning"></i>Transaksi Pending
              </a>
            </li>
          </ul>
        </li>

        <!-- PROSES - TANPA ACTIVE -->
        <li class="nav-item">
          <a class="nav-link" href="proses.php">
            <i class="fas fa-sync-alt me-1"></i>Proses
          </a>
        </li>
        
        <!-- MASTER DATA - TANPA ACTIVE -->
        <li class="nav-item">
          <a class="nav-link" href="master_data.php">
            <i class="fas fa-database me-1"></i>Master Data
          </a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link" href="setting.php">
            <i class="fas fa-cog me-1"></i>Setting
          </a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link" href="laporan.php">
            <i class="fas fa-chart-bar me-1"></i>Laporan
          </a>
        </li>
        
        <li class="nav-item ms-2">
          <a class="btn btn-outline-light" href="logout.php">
            <i class="fas fa-sign-out-alt me-1"></i>Logout
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>