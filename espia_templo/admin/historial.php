<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = getDB();

$ranking = $pdo->query("SELECT * FROM puntajes ORDER BY puntos DESC LIMIT 20")->fetchAll();
$partidas= $pdo->query("SELECT p.*, e.titulo escena_titulo, e.dificultad FROM partidas p JOIN escenas e ON e.id=p.escena_id ORDER BY p.jugada_en DESC LIMIT 50")->fetchAll();
$totales = $pdo->query("SELECT COUNT(*) t, SUM(ganador='espia') ge, SUM(ganador='grupo') gg FROM partidas")->fetch();
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Historial — Espía en el Templo</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0F0A14;--surf:#1A1220;--card:#221828;--rojo:#C02030;--dor:#C89820;--dor2:#F0C030;--crema:#F5EDD8;--gris:#706880;--gris2:#9888A8;--ver:#206840;--radio:10px}
body{background:var(--bg);font-family:'DM Sans',sans-serif;color:var(--crema);min-height:100vh}
nav{background:var(--surf);padding:.8rem 2rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;border-bottom:1px solid rgba(192,32,48,.2)}
nav h1{font-family:'Bebas Neue';font-size:1.3rem;letter-spacing:.08em}
nav a{color:var(--gris2);font-size:.85rem;text-decoration:none;transition:color .2s}
nav a:hover,nav a.on{color:var(--dor2)}
.wrap{max-width:1100px;margin:0 auto;padding:1.5rem;display:grid;grid-template-columns:340px 1fr;gap:1.5rem}
@media(max-width:800px){.wrap{grid-template-columns:1fr}}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:1.2rem}
.sb{background:var(--card);border-radius:var(--radio);padding:.9rem;text-align:center;border:1px solid rgba(255,255,255,.05)}
.sn{font-family:'Bebas Neue';font-size:2rem;line-height:1}
.sl{font-size:.7rem;color:var(--gris2);text-transform:uppercase;letter-spacing:.06em}
.card{background:var(--card);border-radius:var(--radio);border:1px solid rgba(255,255,255,.06);overflow:hidden}
.ct{padding:.9rem 1.1rem;font-family:'Bebas Neue';letter-spacing:.06em;border-bottom:1px solid rgba(255,255,255,.05)}
table{width:100%;border-collapse:collapse}
th,td{padding:.6rem .9rem;font-size:.82rem;border-bottom:1px solid rgba(255,255,255,.04);text-align:left}
th{font-size:.68rem;font-weight:500;color:var(--gris2);text-transform:uppercase;letter-spacing:.06em;background:rgba(255,255,255,.02)}
tr:last-child td{border-bottom:none}
.pts{color:var(--dor2);font-weight:600}
.ok{color:#50D080}
.b{display:inline-block;font-size:.68rem;font-weight:600;padding:2px 8px;border-radius:20px}
.be{background:rgba(192,32,48,.12);color:#FF6070}
.bg{background:rgba(32,104,64,.12);color:#50D080}
.bf{background:rgba(32,104,64,.1);color:#50D080}
.bm{background:rgba(200,152,32,.1);color:var(--dor2)}
.bd{background:rgba(192,32,48,.1);color:#FF6070}
.rank-pos{font-family:'Bebas Neue';font-size:1.1rem}
.gold{color:#FFD700} .silv{color:#C0C0C0} .bron{color:#CD7F32}
</style>
</head>
<body>
<nav>
  <h1>👁 Espía en el Templo</h1>
  <a href="index.php">Escenas</a>
  <a href="historial.php" class="on">Historial</a>
  <a href="../index.php">← Jugar</a>
</nav>
<div class="wrap">
  <div>
    <div class="stats">
      <div class="sb"><div class="sn"><?=$totales['t']?></div><div class="sl">Partidas</div></div>
      <div class="sb"><div class="sn" style="color:#FF6070"><?=$totales['ge']?></div><div class="sl">Espía ganó</div></div>
      <div class="sb"><div class="sn" style="color:#50D080"><?=$totales['gg']?></div><div class="sl">Grupo ganó</div></div>
    </div>
    <div class="card">
      <div class="ct">🏆 Ranking de jugadores</div>
      <table>
        <thead><tr><th>#</th><th>Jugador</th><th>Pts</th><th>Victorias</th><th>Como espía</th></tr></thead>
        <tbody>
        <?php foreach($ranking as $i=>$r): ?>
        <tr>
          <td class="rank-pos <?=$i===0?'gold':($i===1?'silv':($i===2?'bron':''))?>"><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($r['jugador'])?></strong></td>
          <td class="pts"><?=$r['puntos']?></td>
          <td class="ok"><?=$r['victorias']?> / <?=$r['victorias']+$r['derrotas']?></td>
          <td style="color:#FF6070"><?=$r['como_espia']?>x</td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$ranking): ?><tr><td colspan="5" style="text-align:center;color:var(--gris2);padding:2rem">Sin partidas aún</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="ct">📋 Últimas 50 partidas</div>
    <table>
      <thead><tr><th>Escena</th><th>Dif.</th><th>Espía</th><th>¿Adivinó?</th><th>Ganador</th><th>Fecha</th></tr></thead>
      <tbody>
      <?php foreach($partidas as $p): ?>
      <tr>
        <td><strong><?=htmlspecialchars($p['escena_titulo'])?></strong></td>
        <td><span class="b b<?=substr($p['dificultad'],0,1)?>"><?=ucfirst($p['dificultad'])?></span></td>
        <td style="color:#FF6070"><?=htmlspecialchars($p['espia_nombre'])?></td>
        <td><?=$p['espia_adivinado']?'<span class="ok">✓ Sí</span>':'<span style="color:var(--gris2)">No</span>'?></td>
        <td><span class="b <?=$p['ganador']==='espia'?'be':'bg'?>"><?=$p['ganador']==='espia'?'Espía':'Grupo'?></span></td>
        <td style="color:var(--gris2);font-size:.78rem"><?=date('d/m/y H:i', strtotime($p['jugada_en']))?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$partidas): ?><tr><td colspan="6" style="text-align:center;color:var(--gris2);padding:2rem">Sin partidas aún</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
