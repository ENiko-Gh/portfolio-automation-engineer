<?php
session_start();
require_once 'auth_check.php';

if (!function_exists('getAdminName')) {
    function getAdminName(): string
    {
        if (!empty($_SESSION['admin_name'])) return $_SESSION['admin_name'];
        if (!empty($_SESSION['admin_user'])) return $_SESSION['admin_user'];
        return 'Admin';
    }
}

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
$db = new Database();
$page_title = 'Me Gusta & Calificaciones';

// ── DATOS DE LIKES ────────────────────────────────────────────
$likes = [];
try {
    $likes = $db->fetchAll(
        "SELECT
            l.project_id,
            COALESCE(l.reaction_type, 'like') AS reaction_type,
            COUNT(*) AS total,
            MAX(l.liked_at) AS last_at
         FROM likes l
         GROUP BY l.project_id, reaction_type
         ORDER BY l.project_id, reaction_type"
    );
} catch (Exception $e) {
    // Si no tiene reaction_type, consulta simple
    try {
        $likes = $db->fetchAll(
            "SELECT project_id, 'like' AS reaction_type,
                    COUNT(*) AS total, MAX(liked_at) AS last_at
             FROM likes
             GROUP BY project_id
             ORDER BY project_id"
        );
    } catch (Exception $e2) {
    }
}

// Agrupar por project_id
$likesByProject = [];
foreach ($likes as $row) {
    $pid = $row['project_id'];
    if (!isset($likesByProject[$pid])) {
        $likesByProject[$pid] = ['like' => 0, 'dislike' => 0, 'wow' => 0, 'last_at' => ''];
    }
    $likesByProject[$pid][$row['reaction_type']] = (int)$row['total'];
    if ($row['last_at'] > $likesByProject[$pid]['last_at']) {
        $likesByProject[$pid]['last_at'] = $row['last_at'];
    }
}

// ── DATOS DE RATINGS (estrellas) ──────────────────────────────
$ratings = [];
try {
    $ratings = $db->fetchAll(
        "SELECT
            r.project_id,
            ROUND(AVG(r.rating),1) AS avg_rating,
            COUNT(*) AS total_ratings,
            MIN(r.rating) AS min_rating,
            MAX(r.rating) AS max_rating,
            SUM(r.rating = 5) AS five_stars,
            SUM(r.rating = 4) AS four_stars,
            SUM(r.rating = 3) AS three_stars,
            SUM(r.rating = 2) AS two_stars,
            SUM(r.rating = 1) AS one_star,
            MAX(r.updated_at) AS last_at
         FROM ratings r
         GROUP BY r.project_id
         ORDER BY avg_rating DESC"
    );
} catch (Exception $e) {
}

// ── TOTAL GLOBAL ──────────────────────────────────────────────
$totalLikes    = array_sum(array_column($likes, 'total'));
$totalRatings  = count($ratings) ? array_sum(array_column($ratings, 'total_ratings')) : 0;
$globalAvg     = 0;
if ($totalRatings > 0) {
    $sum = array_sum(array_map(fn($r) => $r['avg_rating'] * $r['total_ratings'], $ratings));
    $globalAvg = round($sum / $totalRatings, 1);
}

// Nombres de proyectos
$projectNames = [
    '1' => 'Compras Públicas (Case 1)',
    '2' => 'Quesinor ISO 27001 (Case 2)',
    '3' => 'Milagro de Vida (Case 3)',
];

include 'header.php';
?>

<div style="max-width:1100px;margin:0 auto">

    <!-- RESUMEN GLOBAL -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px">
        <?php
        $totalLikes2 = 0;
        $totalDislikes = 0;
        $totalWow = 0;
        foreach ($likesByProject as $pid => $data) {
            $totalLikes2    += $data['like'];
            $totalDislikes  += $data['dislike'];
            $totalWow       += $data['wow'];
        }
        $cards = [
            ['❤️', 'Me Gusta', $totalLikes2,   '#FC8181', 'rgba(252,129,129,.1)'],
            ['👎', 'Aversión', $totalDislikes, '#F6AD55', 'rgba(246,173,85,.1)'],
            ['🤩', 'Guau',     $totalWow,      '#A78BFA', 'rgba(167,139,250,.1)'],
            ['⭐', 'Promedio', $globalAvg ? $globalAvg . '/5' : '–', '#F6D860', 'rgba(246,216,96,.1)'],
        ];
        foreach ($cards as [$ico, $label, $val, $color, $bg]):
        ?>
            <div style="background:<?= $bg ?>;border:1px solid <?= $color ?>33;border-radius:12px;
                padding:18px;text-align:center">
                <div style="font-size:28px"><?= $ico ?></div>
                <div style="font-size:26px;font-weight:700;color:<?= $color ?>;font-family:monospace;margin-top:4px">
                    <?= $val ?>
                </div>
                <div style="font-size:11px;color:#7A90B0;margin-top:2px"><?= $label ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- LIKES POR PROYECTO -->
    <div style="background:#1A1A1A;border:1px solid #2D2D2D;border-radius:12px;
            overflow:hidden;margin-bottom:24px">
        <div style="padding:16px 20px;border-bottom:1px solid #2D2D2D;background:#111;
                display:flex;align-items:center;justify-content:space-between">
            <h3 style="color:#EDEDED;font-size:15px;font-weight:700;margin:0">
                ❤️ Reacciones por Proyecto
            </h3>
            <span style="font-size:12px;color:#6B6B6B">
                <?= $totalLikes2 + $totalDislikes + $totalWow ?> reacciones totales
            </span>
        </div>

        <?php if (empty($likesByProject)): ?>
            <div style="padding:40px;text-align:center;color:#4A5568">
                <div style="font-size:32px;margin-bottom:8px">❤️</div>
                <p>Aún no hay reacciones. Comparte tu portafolio para obtener las primeras.</p>
            </div>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="background:#111;color:#6B6B6B;font-size:11px;text-transform:uppercase;
                       letter-spacing:.5px">
                        <th style="padding:10px 20px;text-align:left">Proyecto</th>
                        <th style="padding:10px;text-align:center">❤️ Me Gusta</th>
                        <th style="padding:10px;text-align:center">👎 Aversión</th>
                        <th style="padding:10px;text-align:center">🤩 Guau</th>
                        <th style="padding:10px;text-align:center">Total</th>
                        <th style="padding:10px 20px;text-align:right">Última reacción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($likesByProject as $pid => $data):
                        $name  = $projectNames[$pid] ?? "Proyecto #{$pid}";
                        $total = $data['like'] + $data['dislike'] + $data['wow'];
                    ?>
                        <tr style="border-bottom:1px solid #1A1A1A">
                            <td style="padding:12px 20px;font-weight:600;color:#EDEDED"><?= htmlspecialchars($name) ?></td>
                            <td style="padding:12px;text-align:center">
                                <span style="background:rgba(252,129,129,.15);color:#FC8181;padding:3px 10px;
                             border-radius:12px;font-weight:700;font-family:monospace">
                                    <?= $data['like'] ?>
                                </span>
                            </td>
                            <td style="padding:12px;text-align:center">
                                <span style="background:rgba(246,173,85,.15);color:#F6AD55;padding:3px 10px;
                             border-radius:12px;font-weight:700;font-family:monospace">
                                    <?= $data['dislike'] ?>
                                </span>
                            </td>
                            <td style="padding:12px;text-align:center">
                                <span style="background:rgba(167,139,250,.15);color:#A78BFA;padding:3px 10px;
                             border-radius:12px;font-weight:700;font-family:monospace">
                                    <?= $data['wow'] ?>
                                </span>
                            </td>
                            <td style="padding:12px;text-align:center;font-weight:700;color:#EDEDED;
                       font-family:monospace"><?= $total ?></td>
                            <td style="padding:12px 20px;text-align:right;font-size:11px;color:#6B6B6B">
                                <?= $data['last_at'] ? date('d M Y, H:i', strtotime($data['last_at'])) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- RATINGS (ESTRELLAS) POR PROYECTO -->
    <div style="background:#1A1A1A;border:1px solid #2D2D2D;border-radius:12px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #2D2D2D;background:#111;
                display:flex;align-items:center;justify-content:space-between">
            <h3 style="color:#EDEDED;font-size:15px;font-weight:700;margin:0">
                ⭐ Calificaciones (Estrellas) por Proyecto
            </h3>
            <span style="font-size:12px;color:#6B6B6B">
                <?= $totalRatings ?> calificaciones totales
                <?= $globalAvg ? "· Promedio global: {$globalAvg}/5" : '' ?>
            </span>
        </div>

        <?php if (empty($ratings)): ?>
            <div style="padding:40px;text-align:center;color:#4A5568">
                <div style="font-size:32px;margin-bottom:8px">⭐</div>
                <p>Aún no hay calificaciones de estrellas.</p>
                <p style="font-size:12px;color:#4A5568;margin-top:6px">
                    Los visitantes pueden calificar con ★★★★★ debajo de cada caso de estudio.
                </p>
            </div>
        <?php else: ?>
            <div style="padding:20px;display:flex;flex-direction:column;gap:20px">
                <?php foreach ($ratings as $r):
                    $name = $projectNames[$r['project_id']] ?? "Proyecto #{$r['project_id']}";
                    $avg  = (float)$r['avg_rating'];
                    $full = floor($avg);
                    $half = ($avg - $full) >= 0.5;
                ?>
                    <div style="background:#111;border:1px solid #2D2D2D;border-radius:10px;padding:18px">
                        <div style="display:flex;align-items:center;justify-content:space-between;
                    flex-wrap:wrap;gap:12px;margin-bottom:14px">
                            <div>
                                <div style="font-weight:700;color:#EDEDED;font-size:14px">
                                    <?= htmlspecialchars($name) ?>
                                </div>
                                <div style="font-size:11px;color:#6B6B6B;margin-top:2px">
                                    <?= $r['total_ratings'] ?> calificaciones ·
                                    Última: <?= $r['last_at'] ? date('d M Y', strtotime($r['last_at'])) : '—' ?>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px">
                                <span style="font-size:32px;font-weight:800;color:#F6D860;font-family:monospace">
                                    <?= number_format($avg, 1) ?>
                                </span>
                                <div>
                                    <div style="color:#F6D860;font-size:18px;line-height:1">
                                        <?= str_repeat('★', $full) ?><?= $half ? '½' : '' ?><?= str_repeat('☆', 5 - $full - ($half ? 1 : 0)) ?>
                                    </div>
                                    <div style="font-size:10px;color:#6B6B6B">/ 5.0 estrellas</div>
                                </div>
                            </div>
                        </div>

                        <!-- Distribución de estrellas -->
                        <div style="display:flex;flex-direction:column;gap:6px">
                            <?php
                            $starData = [
                                5 => (int)$r['five_stars'],
                                4 => (int)$r['four_stars'],
                                3 => (int)$r['three_stars'],
                                2 => (int)$r['two_stars'],
                                1 => (int)$r['one_star'],
                            ];
                            foreach ($starData as $stars => $count):
                                $pct = $r['total_ratings'] > 0 ? round($count / $r['total_ratings'] * 100) : 0;
                            ?>
                                <div style="display:flex;align-items:center;gap:8px;font-size:12px">
                                    <span style="color:#F6D860;width:20px;text-align:right"><?= $stars ?>★</span>
                                    <div style="flex:1;height:8px;background:#2D2D2D;border-radius:4px;overflow:hidden">
                                        <div style="width:<?= $pct ?>%;height:100%;
                            background:<?= $pct > 60 ? '#48BB78' : ($pct > 30 ? '#F6D860' : '#FC8181') ?>;
                            border-radius:4px;transition:width .3s"></div>
                                    </div>
                                    <span style="color:#6B6B6B;width:28px"><?= $count ?></span>
                                    <span style="color:#4A5568;width:32px"><?= $pct ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
<?php include 'footer.php'; ?>