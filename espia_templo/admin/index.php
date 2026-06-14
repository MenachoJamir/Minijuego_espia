<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $titulo     = trim($_POST['titulo'] ?? '');
        $desc       = trim($_POST['descripcion'] ?? '');
        $libro      = trim($_POST['libro'] ?? '');
        $dificultad = $_POST['dificultad'] ?? 'medio';
        $pistas_raw = array_filter(array_map('trim', $_POST['pistas'] ?? []));

        if (!$titulo) { $msg = ['t'=>'e','x'=>'El título es obligatorio.']; }
        else {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("UPDATE escenas SET titulo=?,descripcion=?,libro=?,dificultad=? WHERE id=?")
                    ->execute([$titulo,$desc,$libro,$dificultad,$id]);
                $pdo->prepare("DELETE FROM pistas_escena WHERE escena_id=?")->execute([$id]);
            } else {
                $pdo->prepare("INSERT INTO escenas (titulo,descripcion,libro,dificultad) VALUES (?,?,?,?)")
                    ->execute([$titulo,$desc,$libro,$dificultad]);
                $id = (int)$pdo->lastInsertId();
            }
            $s = $pdo->prepare("INSERT INTO pistas_escena (escena_id,pista) VALUES (?,?)");
            foreach ($pistas_raw as $p) $s->execute([$id,$p]);
            $msg = ['t'=>'ok','x'=>'Escena guardada.'];
        }
    }
    if ($accion === 'toggle') {
        $pdo->prepare("UPDATE escenas SET activa = NOT activa WHERE id=?")->execute([(int)$_POST['id']]);
        header('Location: index.php'); exit;
    }
    if ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM escenas WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = ['t'=>'ok','x'=>'Escena eliminada.'];
    }
}

$escenas = $pdo->query("SELECT e.*, COUNT(p.id) np FROM escenas e LEFT JOIN pistas_escena p ON p.escena_id=e.id GROUP BY e.id ORDER BY e.id DESC")->fetchAll();
$stats   = $pdo->query("SELECT COUNT(*) t, SUM(activa) a, SUM(dificultad='facil') f, SUM(dificultad='medio') m, SUM(dificultad='dificil') d FROM escenas")->fetch();
$npart   = $pdo->query("SELECT COUNT(*) FROM partidas")->fetchColumn();

$eid = (int)($_GET['e'] ?? 0);
$edit = null;
if ($eid > 0) {
    $edit = $pdo->prepare("SELECT * FROM escenas WHERE id=?"); $edit->execute([$eid]); $edit = $edit->fetch();
    if ($edit) {
        $pp = $pdo->prepare("SELECT pista FROM pistas_escena WHERE escena_id=? ORDER BY id"); $pp->execute([$eid]);
        $edit['pistas'] = $pp->fetchAll(PDO::FETCH_COLUMN);
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Espía en el Templo</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0F0A14;--surf:#1A1220;--card:#221828;--rojo:#C02030;--rojo2:#E83040;--dor:#C89820;--dor2:#F0C030;--crema:#F5EDD8;--gris:#706880;--gris2:#9888A8;--ver:#206840;--radio:10px}
body{background:var(--bg);font-family:'DM Sans',sans-serif;color:var(--crema);min-height:100vh}
a{color:var(--dor);text-decoration:none}a:hover{text-decoration:underline}
nav{background:var(--surf);padding:.8rem 2rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;border-bottom:1px solid rgba(192,32,48,.2)}
nav h1{font-family:'Bebas Neue';font-size:1.3rem;letter-spacing:.08em;color:var(--crema)}
nav a{color:var(--gris2);font-size:.85rem;transition:color .2s}
nav a:hover,nav a.on{color:var(--dor2)}
.wrap{max-width:1100px;margin:0 auto;padding:1.5rem;display:grid;grid-template-columns:1fr 360px;gap:1.5rem}
@media(max-width:860px){.wrap{grid-template-columns:1fr}}
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:.6rem;margin-bottom:1.2rem}
.sb{background:var(--card);border-radius:var(--radio);padding:.8rem;text-align:center;border:1px solid rgba(255,255,255,.05)}
.sn{font-family:'Bebas Neue';font-size:1.8rem;color:var(--dor2);line-height:1}
.sl{font-size:.68rem;color:var(--gris2);text-transform:uppercase;letter-spacing:.06em;margin-top:2px}
.card{background:var(--card);border-radius:var(--radio);border:1px solid rgba(255,255,255,.06);overflow:hidden}
.ct{padding:.9rem 1.1rem;font-family:'Bebas Neue';letter-spacing:.06em;font-size:1rem;border-bottom:1px solid rgba(255,255,255,.05)}
table{width:100%;border-collapse:collapse}
th,td{padding:.6rem .9rem;font-size:.82rem;border-bottom:1px solid rgba(255,255,255,.04);text-align:left}
th{font-size:.68rem;font-weight:500;color:var(--gris2);text-transform:uppercase;letter-spacing:.06em;background:rgba(255,255,255,.02)}
tr:last-child td{border-bottom:none}
.b{display:inline-block;font-size:.68rem;font-weight:600;padding:2px 8px;border-radius:20px;letter-spacing:.04em}
.bf{background:rgba(32,104,64,.15);color:#50D080}
.bm{background:rgba(200,152,32,.12);color:var(--dor2)}
.bd{background:rgba(192,32,48,.12);color:#FF6070}
.ba{background:rgba(32,104,64,.1);color:#50D080}
.bi{background:rgba(192,32,48,.1);color:#FF6070}
.btn-sm{display:inline-block;padding:3px 9px;border-radius:5px;font-size:.72rem;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans';transition:all .2s}
.be{background:rgba(200,152,32,.12);color:var(--dor)} .be:hover{background:var(--dor);color:#000}
.bt{background:rgba(32,104,64,.1);color:#50D080}     .bt:hover{background:var(--ver);color:#fff}
.bl{background:rgba(192,32,48,.1);color:#FF6070}     .bl:hover{background:var(--rojo);color:#fff}
.form-c{background:var(--card);border-radius:var(--radio);border:1px solid rgba(192,32,48,.2);padding:1.4rem}
.ft{font-family:'Bebas Neue';font-size:1rem;letter-spacing:.06em;margin-bottom:1.2rem;padding-bottom:.6rem;border-bottom:1px solid rgba(255,255,255,.06)}
.fg{margin-bottom:.9rem}
.fl{display:block;font-size:.68rem;font-weight:500;text-transform:uppercase;letter-spacing:.08em;color:var(--gris2);margin-bottom:.3rem}
.fi,.fs,.ft-a{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:6px;padding:.55rem .8rem;color:var(--crema);font-family:'DM Sans';font-size:.88rem;outline:none;transition:border-color .2s;resize:vertical}
.fi:focus,.fs:focus,.ft-a:focus{border-color:rgba(192,32,48,.4)}
.dg{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}
.prow{display:flex;gap:.4rem;align-items:center;margin-bottom:.4rem}
.prow span{font-size:.7rem;color:var(--gris2);min-width:1.5rem;text-align:right}
.prow input{flex:1;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:5px;padding:.45rem .7rem;color:var(--crema);font-family:'DM Sans';font-size:.85rem;outline:none}
.prow input:focus{border-color:rgba(192,32,48,.35)}
.apb{width:100%;font-size:.78rem;color:var(--dor);background:none;border:1px dashed rgba(200,152,32,.3);border-radius:6px;padding:4px;cursor:pointer;transition:all .2s;margin-bottom:.6rem}
.apb:hover{background:rgba(200,152,32,.08)}
.bguardar{width:100%;padding:.8rem;background:var(--rojo);border:none;border-radius:var(--radio);color:#fff;font-family:'Bebas Neue';font-size:1rem;letter-spacing:.08em;cursor:pointer;transition:all .2s}
.bguardar:hover{background:var(--rojo2)}
.alerta{padding:.7rem .9rem;border-radius:var(--radio);margin-bottom:1rem;font-size:.85rem}
.aok{background:rgba(32,104,64,.15);border:1px solid rgba(32,104,64,.25);color:#50D080}
.aerr{background:rgba(192,32,48,.12);border:1px solid rgba(192,32,48,.25);color:#FF6070}
.acc{display:flex;justify-content:flex-end;gap:.5rem;margin-top:.8rem}
</style>
</head>
<body>
<nav>
  <h1>👁 Espía en el Templo</h1>
  <a href="index.php" class="on">Escenas</a>
  <a href="historial.php">Historial</a>
  <a href="../index.php">← Jugar</a>
</nav>
<div class="wrap">
  <div>
    <div class="stats">
      <div class="sb"><div class="sn"><?=$stats['t']?></div><div class="sl">Escenas</div></div>
      <div class="sb"><div class="sn"><?=$stats['a']?></div><div class="sl">Activas</div></div>
      <div class="sb"><div class="sn"><?=$stats['f']?></div><div class="sl">Fácil</div></div>
      <div class="sb"><div class="sn"><?=$stats['m']?></div><div class="sl">Media</div></div>
      <div class="sb"><div class="sn"><?=$npart?></div><div class="sl">Partidas</div></div>
    </div>
    <?php if($msg): ?><div class="alerta <?=$msg['t']==='ok'?'aok':'aerr'?>"><?=htmlspecialchars($msg['x'])?></div><?php endif; ?>
    <div class="card">
      <div class="ct">Escenas bíblicas</div>
      <table>
        <thead><tr><th>Título</th><th>Libro</th><th>Dif.</th><th>Pistas</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach($escenas as $e): ?>
        <tr>
          <td><strong><?=htmlspecialchars($e['titulo'])?></strong></td>
          <td style="color:var(--gris2);font-size:.8rem"><?=htmlspecialchars($e['libro']??'')?></td>
          <td><span class="b b<?=substr($e['dificultad'],0,1)?>"><?=ucfirst($e['dificultad'])?></span></td>
          <td style="color:var(--gris2)"><?=$e['np']?></td>
          <td><span class="b <?=$e['activa']?'ba':'bi'?>"><?=$e['activa']?'Activa':'Inactiva'?></span></td>
          <td style="white-space:nowrap">
            <a href="?e=<?=$e['id']?>" class="btn-sm be">Editar</a>
            <form method="post" style="display:inline"><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?=$e['id']?>"><button type="submit" class="btn-sm bt"><?=$e['activa']?'Desactivar':'Activar'?></button></form>
            <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar?')"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=$e['id']?>"><button type="submit" class="btn-sm bl">Eliminar</button></form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div>
    <div class="form-c">
      <div class="ft"><?=$edit?'✏ Editar: '.htmlspecialchars($edit['titulo']):'+ Nueva escena'?></div>
      <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <?php if($edit): ?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif; ?>
        <div class="fg"><label class="fl">Título de la escena</label><input class="fi" type="text" name="titulo" required placeholder="Ej: La Última Cena" value="<?=htmlspecialchars($edit['titulo']??'')?>"></div>
        <div class="fg"><label class="fl">Libro / Referencia</label><input class="fi" type="text" name="libro" placeholder="Ej: Juan 13" value="<?=htmlspecialchars($edit['libro']??'')?>"></div>
        <div class="dg fg">
          <div><label class="fl">Dificultad</label><select class="fs" name="dificultad">
            <option value="facil" <?=($edit['dificultad']??'')==='facil'?'selected':''?>>Fácil</option>
            <option value="medio" <?=($edit['dificultad']??'medio')==='medio'?'selected':''?>>Media</option>
            <option value="dificil" <?=($edit['dificultad']??'')==='dificil'?'selected':''?>>Difícil</option>
          </select></div>
        </div>
        <div class="fg"><label class="fl">Descripción breve</label><textarea class="ft-a" name="descripcion" rows="3" placeholder="Describe brevemente la escena..."><?=htmlspecialchars($edit['descripcion']??'')?></textarea></div>
        <div class="fg">
          <label class="fl">Pistas de contexto (para los agentes)</label>
          <div id="prows">
            <?php $pp=$edit['pistas']??['','','','','']; foreach($pp as $i=>$pt): ?>
            <div class="prow"><span>#<?=$i+1?></span><input type="text" name="pistas[]" placeholder="Pista <?=$i+1?>" value="<?=htmlspecialchars($pt)?>"></div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="apb" onclick="addPista()">+ Pista</button>
        </div>
        <button type="submit" class="bguardar">Guardar escena</button>
        <?php if($edit): ?><div class="acc"><a href="index.php" style="font-size:.8rem;color:var(--gris2)">Cancelar</a></div><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<script>
let pc = document.querySelectorAll('.prow').length;
function addPista(){pc++;const r=document.createElement('div');r.className='prow';r.innerHTML=`<span>#${pc}</span><input type="text" name="pistas[]" placeholder="Pista ${pc}">`;document.getElementById('prows').appendChild(r);}
</script>
</body></html>
