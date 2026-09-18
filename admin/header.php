<?php
/**
 * admin/header.php — VERSIÓN CORREGIDA
 * FIX: define getAdminName() — tu versión original la llamaba sin definirla → fatal error
 * NUEVO: enlace CRM en menú con badge de contactos potenciales
 */
if (!function_exists('getAdminName')) {
    function getAdminName(): string {
        if (!empty($_SESSION['admin_name'])) return $_SESSION['admin_name'];
        if (!empty($_SESSION['admin_user'])) return $_SESSION['admin_user'];
        return 'Admin';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Dashboard') ?> — Admin Panel</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">📊 Admin Panel</a>
        </div>
        <nav>
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='dashboard.php'?'active':'' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="crm.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='crm.php'?'active':'' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/><line x1="18" y1="14" x2="22" y2="14"/><line x1="20" y1="12" x2="20" y2="16"/></svg>
                        <span>CRM Pipeline</span>
                        <?php
                        try {
                            if (!isset($db)) { require_once __DIR__.'/../includes/db.php'; $db=new Database(); }
                            if ($db->tableExists('visitors')) {
                                $pot=$db->count('visitors',"crm_status='potential'");
                                if($pot>0) echo "<span style='background:#FC8181;color:#000;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:auto'>{$pot}</span>";
                            }
                        } catch(Exception $e){}
                        ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="visitors.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='visitors.php'?'active':'' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        <span>Visitors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="appointments.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='appointments.php'?'active':'' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span>Appointments</span>
                        <?php
                        try {
                            if(!isset($db)){require_once __DIR__.'/../includes/db.php';$db=new Database();}
                            if($db->tableExists('appointments')){$p=$db->count('appointments','status = ?',['pending']);if($p>0)echo "<span style='background:#F59E0B;color:#000;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:auto'>{$p}</span>";}
                        }catch(Exception $e){}
                        ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="comments.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='comments.php'?'active':'' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        <span>Comments</span>
                        <?php
                        try {
                            if(!isset($db)){require_once __DIR__.'/../includes/db.php';$db=new Database();}
                            if($db->tableExists('comments')){$pc=$db->count('comments','is_approved = 0');if($pc>0)echo "<span style='background:#F59E0B;color:#000;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:auto'>{$pc}</span>";}
                        }catch(Exception $e){}
                        ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='settings.php'?'active':'' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../index.html" class="nav-link" target="_blank">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        <span>View Site</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div style="margin-top:auto;padding-top:24px;border-top:1px solid var(--border-color)">
            <a href="logout.php" class="nav-link" style="color:#EF4444">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    <main class="main-content">
        <div class="topbar">
            <h1 class="topbar-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
            <div class="topbar-actions">
                <div class="user-menu">
                    <div class="user-avatar"><?= strtoupper(substr(getAdminName(),0,1)) ?></div>
                    <span><?= htmlspecialchars(getAdminName()) ?></span>
                </div>
            </div>
        </div>
