<?php
session_start();
?>
<html>
<head>
<?php
include("links.php");
?>
<style>
:root {
    --bg-1: #f3f4f6;
    --bg-2: #fffaf0;
    --ink: #1f2937;
    --panel: #ffffff;
    --line: #e5e7eb;
    --brand: #0f766e;
}

.body-style {
    margin: 0;
    color: var(--ink);
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background: radial-gradient(circle at 0% 0%, #fef3c7 0%, transparent 24%),
                radial-gradient(circle at 100% 0%, #dbeafe 0%, transparent 28%),
                linear-gradient(180deg, var(--bg-2), var(--bg-1));
}

.header {
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid var(--line);
    backdrop-filter: blur(8px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    padding: 12px 20px;
}

.main-platform {
    max-width: 1420px;
    margin: 0 auto;
    padding: 20px 18px 28px;
}

.dashboard-headline {
    margin: 0 0 14px;
    padding: 16px 18px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: linear-gradient(135deg, #0f766e 0%, #155e75 100%);
    color: #ecfeff;
    box-shadow: 0 14px 36px rgba(8, 47, 73, 0.22);
}

.dashboard-headline h2 {
    margin: 0 0 6px;
}

.dashboard-headline p {
    margin: 0;
    color: #cffafe;
}

.layout {
    display: grid;
    grid-template-columns: 290px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.sidebar > * {
    position: sticky;
    top: 14px;
}

.form-entry {
    border-radius: 14px;
    border: 1px solid var(--line);
    background: var(--panel);
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
    padding: 14px;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.quick-card {
    border: 1px solid var(--line);
    background: #fff;
    border-radius: 12px;
    padding: 14px;
    text-decoration: none;
    color: #1f2937;
    transition: all .2s ease;
}

.quick-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.1);
    border-color: #86efac;
    background: #f0fdf4;
}

.quick-card h4 {
    margin: 0 0 6px;
    font-size: 0.95rem;
}

.quick-card p {
    margin: 0;
    font-size: 0.85rem;
    color: #64748b;
}

@media (max-width: 980px) {
    .layout {
        grid-template-columns: 1fr;
    }
    .sidebar > * {
        position: static;
    }
    .quick-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body class="body-style">
<div class="header">
<?php
include("menu.php");
?>		
</div>
<div class="main-platform">
    <div class="dashboard-headline">
        <h2>Super Admin Dashboard</h2>
        <p>Manage core setup, semester operations, and academic workflows from one place.</p>
    </div>
    <div class="layout">
        <div class="sidebar">
            <div class="form-entry">
                <?php
                include("welcome.php");
                include("menuboard.php");
                ?>
            </div>
        </div>
        <div class="content">
            <div class="form-entry">
                <div class="quick-grid">
                    <a class="quick-card" href="batch-entry.php">
                        <h4><i class="fa fa-calendar"></i> Batch Setup</h4>
                        <p>Create and manage school batches/years.</p>
                    </a>
                    <a class="quick-card" href="term-registry.php">
                        <h4><i class="fa fa-plus"></i> Semester Registry</h4>
                        <p>Register terms for students by class and batch.</p>
                    </a>
                    <a class="quick-card" href="promotion-center.php">
                        <h4><i class="fa fa-level-up"></i> Promotion Center</h4>
                        <p>Promote classes or students with tracking.</p>
                    </a>
                    <a class="quick-card" href="class-registry.php">
                        <h4><i class="fa fa-users"></i> Class Registry</h4>
                        <p>Maintain active class placement records.</p>
                    </a>
                    <a class="quick-card" href="student-history.php">
                        <h4><i class="fa fa-history"></i> Student History</h4>
                        <p>Review report and progression history.</p>
                    </a>
                    <a class="quick-card" href="view-class-registry.php">
                        <h4><i class="fa fa-eye"></i> View Class Registry</h4>
                        <p>See class membership by selected batch.</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
