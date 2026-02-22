<?php
session_start();
?>

<nav class="navbar navbar-expand-lg bg-dark navbar-dark shadow-sm fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
            <i class="bi bi-trophy-fill me-2 text-warning"></i>
            KSPSA - Sports Event Manager
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (isset($_SESSION['role'])): ?>
                    <?php if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === ($_SESSION['role'] === 'super_admin' ? 'super_admin.php' : 'admin.php') ? 'active' : ''; ?>" 
                               href="<?php echo $_SESSION['role'] === 'super_admin' ? 'super_admin.php' : 'admin.php'; ?>">
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'scoring.php' ? 'active' : ''; ?>" href="scoring.php">Scoring</a>
                        </li>
                    <?php elseif ($_SESSION['role'] === 'district_head'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'district_head.php' ? 'active' : ''; ?>" href="district_head.php">
                                Dashboard
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['role'])): ?>
                    <li class="nav-item">
                        <a class="btn btn-outline-danger px-4" href="logout.php">Logout</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>