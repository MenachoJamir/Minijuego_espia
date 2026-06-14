<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// ══════════════════════════════════════════════
//  API AJAX
// ══════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $accion = $_POST['accion'] ?? '';
    $pdo    = getDB();

    // ── Iniciar partida ──────────────────────
    if ($accion === 'iniciar') {
        $nombres    = array_values(array_filter(array_map('trim', json_decode($_POST['nombres'] ?? '[]', true))));
        $dificultad = $_POST['dificultad'] ?? 'todos';

        if (count($nombres) < 4) { echo json_encode(['error' => 'Mínimo 4 jugadores']); exit; }
        if (count($nombres) > 8) { echo json_encode(['error' => 'Máximo 8 jugadores']); exit; }

        // Verificar nombres únicos
        if (count($nombres) !== count(array_unique($nombres))) {
            echo json_encode(['error' => 'Los nombres deben ser únicos']); exit;
        }

        // Elegir escena aleatoria
        $where  = $dificultad !== 'todos' ? "WHERE activa=1 AND dificultad=?" : "WHERE activa=1";
        $params = $dificultad !== 'todos' ? [$dificultad] : [];
        $stmt   = $pdo->prepare("SELECT * FROM escenas $where ORDER BY RAND() LIMIT 1");
        $stmt->execute($params);
        $escena = $stmt->fetch();
        if (!$escena) { echo json_encode(['error' => 'No hay escenas disponibles']); exit; }

        // Pistas de contexto
        $pistas = $pdo->prepare("SELECT pista FROM pistas_escena WHERE escena_id=? ORDER BY RAND() LIMIT 4");
        $pistas->execute([$escena['id']]);
        $escena['pistas'] = $pistas->fetchAll(PDO::FETCH_COLUMN);

        // Elegir espía aleatoriamente
        $idx_espia = array_rand($nombres);

        // Construir jugadores
        $jugadores = [];
        foreach ($nombres as $i => $nombre) {
            $jugadores[] = [
                'nombre'    => $nombre,
                'es_espia'  => ($i === $idx_espia),
                'votos'     => 0,
                'ha_votado' => false,
                'eliminado' => false,
                'color_idx' => $i,
            ];
        }
        shuffle($jugadores); // mezclar orden de turnos

        // Re-buscar índice del espía tras shuffle
        $espia_nombre = $nombres[$idx_espia];

        $_SESSION['juego'] = [
            'escena'         => $escena,
            'jugadores'      => $jugadores,
            'espia_nombre'   => $espia_nombre,
            'fase'           => 'revelar',    // revelar | preguntas | votacion | resultado
            'turno_idx'      => 0,            // quién pregunta ahora
            'ronda'          => 1,
            'max_rondas'     => count($jugadores),
            'preguntas_log'  => [],           // historial de preguntas
            'revelar_idx'    => 0,            // qué jugador está viendo su tarjeta
            'votos_emitidos' => 0,
            'partida_id'     => null,
        ];

        echo json_encode(['ok' => true, 'estado' => buildEstado()]);
        exit;
    }

    // ── Confirmar que el jugador vio su tarjeta ──
    if ($accion === 'confirmar_visto') {
        $s = &$_SESSION['juego'];
        $s['revelar_idx']++;
        if ($s['revelar_idx'] >= count($s['jugadores'])) {
            $s['fase'] = 'preguntas';
            $s['turno_idx'] = 0;
        }
        echo json_encode(['estado' => buildEstado()]);
        exit;
    }

    // ── Registrar pregunta/respuesta ─────────────
    if ($accion === 'log_pregunta') {
        $s = &$_SESSION['juego'];
        $s['preguntas_log'][] = [
            'preguntador' => trim($_POST['preguntador'] ?? ''),
            'interrogado' => trim($_POST['interrogado'] ?? ''),
            'pregunta'    => trim($_POST['pregunta'] ?? ''),
            'respuesta'   => '',
        ];
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($accion === 'log_respuesta') {
        $s = &$_SESSION['juego'];
        $idx = count($s['preguntas_log']) - 1;
        if ($idx >= 0) {
            $s['preguntas_log'][$idx]['respuesta'] = trim($_POST['respuesta'] ?? '');
        }
        // Avanzar turno
        $s['turno_idx'] = ($s['turno_idx'] + 1) % count($s['jugadores']);
        // Siguiente ronda si volvemos al inicio
        if ($s['turno_idx'] === 0) $s['ronda']++;
        // Pasar jugadores eliminados
        $intentos = 0;
        while ($s['jugadores'][$s['turno_idx']]['eliminado'] && $intentos < count($s['jugadores'])) {
            $s['turno_idx'] = ($s['turno_idx'] + 1) % count($s['jugadores']);
            $intentos++;
        }
        echo json_encode(['estado' => buildEstado()]);
        exit;
    }

    // ── Iniciar votación ─────────────────────────
    if ($accion === 'iniciar_votacion') {
        $s = &$_SESSION['juego'];
        $s['fase'] = 'votacion';
        // Resetear votos
        foreach ($s['jugadores'] as &$j) { $j['votos'] = 0; $j['ha_votado'] = false; }
        unset($j);
        $s['votos_emitidos'] = 0;
        echo json_encode(['estado' => buildEstado()]);
        exit;
    }

    // ── Emitir voto ──────────────────────────────
    if ($accion === 'votar') {
        $s           = &$_SESSION['juego'];
        $votante_nom = trim($_POST['votante'] ?? '');
        $acusado_nom = trim($_POST['acusado'] ?? '');

        // Validar votante no haya votado
        foreach ($s['jugadores'] as &$j) {
            if ($j['nombre'] === $votante_nom && !$j['ha_votado']) {
                $j['ha_votado'] = true;
                $s['votos_emitidos']++;
                break;
            }
        }
        unset($j);
        // Registrar voto al acusado
        foreach ($s['jugadores'] as &$j) {
            if ($j['nombre'] === $acusado_nom) { $j['votos']++; break; }
        }
        unset($j);

        // ¿Todos votaron?
        $total_activos = count(array_filter($s['jugadores'], fn($j) => !$j['eliminado']));
        if ($s['votos_emitidos'] >= $total_activos) {
            calcularResultado($s);
        }

        echo json_encode(['estado' => buildEstado()]);
        exit;
    }

    // ── El espía intenta adivinar la escena ──────
    if ($accion === 'espia_adivina') {
        $s        = &$_SESSION['juego'];
        $intento  = strtolower(trim($_POST['intento'] ?? ''));
        $titulo   = strtolower($s['escena']['titulo']);
        $correcto = (similar_text($intento, $titulo) / max(strlen($titulo), 1) * 100 >= 60)
                    || levenshtein($intento, $titulo) <= 4;

        $s['espia_adivinado'] = $correcto;
        $s['fase']            = 'resultado';
        $s['ganador']         = $correcto ? 'espia' : 'grupo';

        guardarPartida($s, $pdo);
        actualizarPuntajes($s, $pdo);

        echo json_encode(['correcto' => $correcto, 'estado' => buildEstado()]);
        exit;
    }

    // ── Nueva ronda ──────────────────────────────
    if ($accion === 'nueva_partida') {
        unset($_SESSION['juego']);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['error' => 'Acción desconocida']); exit;
}

// ── Calcular resultado de votación ──────────────
function calcularResultado(array &$s): void {
    // Jugador más votado
    $max_votos = 0;
    $mas_votado = null;
    foreach ($s['jugadores'] as $j) {
        if (!$j['eliminado'] && $j['votos'] > $max_votos) {
            $max_votos  = $j['votos'];
            $mas_votado = $j['nombre'];
        }
    }
    $s['mas_votado'] = $mas_votado;

    // ¿Es el espía el más votado?
    $es_espia_votado = ($mas_votado === $s['espia_nombre']);

    if ($es_espia_votado) {
        // Espía descubierto → puede intentar adivinar la escena
        $s['fase'] = 'espia_adivinando';
    } else {
        // El grupo falló → espía gana
        $s['fase']   = 'resultado';
        $s['ganador'] = 'espia';
        $s['espia_adivinado'] = false;
    }
}

// ── Guardar partida en historial ─────────────────
function guardarPartida(array &$s, PDO $pdo): void {
    try {
        $pdo->prepare("INSERT INTO partidas (escena_id, jugadores_json, espia_nombre, espia_adivinado, ganador)
                       VALUES (?,?,?,?,?)")
            ->execute([
                $s['escena']['id'],
                json_encode(array_column($s['jugadores'], 'nombre')),
                $s['espia_nombre'],
                $s['espia_adivinado'] ? 1 : 0,
                $s['ganador'],
            ]);
        $s['partida_id'] = (int)$pdo->lastInsertId();
    } catch (Exception $e) {}
}

// ── Actualizar puntajes ──────────────────────────
function actualizarPuntajes(array $s, PDO $pdo): void {
    try {
        foreach ($s['jugadores'] as $j) {
            $esEspia  = $j['es_espia'];
            $gano     = ($s['ganador'] === 'espia') === $esEspia;
            $pts      = $gano ? ($esEspia ? 3 : 1) : 0;
            $pdo->prepare("INSERT INTO puntajes (jugador, victorias, derrotas, como_espia, puntos)
                           VALUES (?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE
                             victorias  = victorias  + VALUES(victorias),
                             derrotas   = derrotas   + VALUES(derrotas),
                             como_espia = como_espia + VALUES(como_espia),
                             puntos     = puntos     + VALUES(puntos)")
                ->execute([$j['nombre'], $gano?1:0, $gano?0:1, $esEspia?1:0, $pts]);
        }
    } catch (Exception $e) {}
}

// ── Estado serializable ──────────────────────────
function buildEstado(): array {
    $s = $_SESSION['juego'];
    $fase = $s['fase'];

    $base = [
        'fase'           => $fase,
        'jugadores'      => array_map(fn($j) => [
            'nombre'    => $j['nombre'],
            'votos'     => $j['votos'],
            'ha_votado' => $j['ha_votado'],
            'eliminado' => $j['eliminado'],
            'color_idx' => $j['color_idx'],
        ], $s['jugadores']),
        'turno_idx'      => $s['turno_idx'],
        'ronda'          => $s['ronda'],
        'max_rondas'     => $s['max_rondas'],
        'votos_emitidos' => $s['votos_emitidos'],
        'preguntas_log'  => array_slice($s['preguntas_log'], -10),
        'revelar_idx'    => $s['revelar_idx'] ?? 0,
        'escena_dificultad' => $s['escena']['dificultad'],
        'escena_libro'      => $s['escena']['libro'],
    ];

    // Datos extra según fase
    switch ($fase) {
        case 'resultado':
        case 'espia_adivinando':
            $base['escena_titulo']  = $s['escena']['titulo'];
            $base['escena_desc']    = $s['escena']['descripcion'];
            $base['espia_nombre']   = $s['espia_nombre'];
            $base['mas_votado']     = $s['mas_votado'] ?? null;
            $base['ganador']        = $s['ganador'] ?? null;
            $base['espia_adivinado']= $s['espia_adivinado'] ?? false;
            break;
    }

    return $base;
}

// ── Datos de tarjeta secreta para un jugador ─────
// Llamada via GET ?tarjeta=NOMBRE
if (isset($_GET['tarjeta'])) {
    header('Content-Type: application/json');
    $nombre = trim($_GET['tarjeta']);
    $s = $_SESSION['juego'] ?? null;
    if (!$s) { echo json_encode(['error' => 'Sin partida']); exit; }

    $jugador = null;
    foreach ($s['jugadores'] as $j) {
        if ($j['nombre'] === $nombre) { $jugador = $j; break; }
    }
    if (!$jugador) { echo json_encode(['error' => 'Jugador no encontrado']); exit; }

    if ($jugador['es_espia']) {
        echo json_encode([
            'tipo'    => 'espia',
            'nombre'  => $nombre,
            'libro'   => $s['escena']['libro'],
            'dificultad' => $s['escena']['dificultad'],
        ]);
    } else {
        echo json_encode([
            'tipo'    => 'agente',
            'nombre'  => $nombre,
            'titulo'  => $s['escena']['titulo'],
            'pistas'  => $s['escena']['pistas'],
            'libro'   => $s['escena']['libro'],
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>El Espía en el Templo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Lora:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- ████ P1: REGISTRO ████ -->
<div id="p-registro" class="pantalla activa">
  <div class="registro-bg">
    <div class="bg-arch"></div>
  </div>
  <div class="registro-inner">
    <div class="logo-area">
      <div class="logo-ojo">
        <svg viewBox="0 0 60 40" width="60" height="40"><ellipse cx="30" cy="20" rx="28" ry="18" fill="none" stroke="currentColor" stroke-width="2.5"/><circle cx="30" cy="20" r="9" fill="currentColor"/><circle cx="33" cy="17" r="3" fill="var(--bg-deep)" opacity="0.6"/></svg>
      </div>
      <h1>El Espía<br>en el Templo</h1>
      <p class="logo-sub">Juego de deducción social bíblica</p>
    </div>

    <div class="reg-form">
      <div class="reg-seccion">
        <div class="reg-label">Jugadores <span id="reg-count">(4)</span></div>
        <div class="reg-qty-row">
          <button class="qty-btn" id="btn-menos">−</button>
          <div class="qty-track">
            <div class="qty-fill" id="qty-fill"></div>
          </div>
          <button class="qty-btn" id="btn-mas">+</button>
        </div>
        <div id="reg-nombres" class="reg-nombres"></div>
      </div>

      <div class="reg-seccion">
        <div class="reg-label">Dificultad de las escenas</div>
        <div class="dif-opciones">
          <label class="dif-opt activa"><input type="radio" name="dif" value="todos" checked><span>Todas</span></label>
          <label class="dif-opt"><input type="radio" name="dif" value="facil"><span>Fácil</span></label>
          <label class="dif-opt"><input type="radio" name="dif" value="medio"><span>Media</span></label>
          <label class="dif-opt"><input type="radio" name="dif" value="dificil"><span>Difícil</span></label>
        </div>
      </div>

      <div class="reg-seccion reglas-mini">
        <div class="reg-label">Cómo se juega</div>
        <div class="regla-item"><span class="regla-num">1</span><span>Cada jugador ve su tarjeta secreta en privado</span></div>
        <div class="regla-item"><span class="regla-num">2</span><span>Un jugador es el <strong>Espía</strong> y no sabe la escena</span></div>
        <div class="regla-item"><span class="regla-num">3</span><span>Por turnos, cada uno hace una pregunta a otro</span></div>
        <div class="regla-item"><span class="regla-num">4</span><span>Votan para expulsar al sospechoso</span></div>
        <div class="regla-item"><span class="regla-num">5</span><span>El espía gana si no lo descubren o adivina la escena</span></div>
      </div>

      <button id="btn-iniciar" class="btn-comenzar">
        Comenzar partida
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
      </button>
      <a href="admin/" class="link-admin">⚙ Administrar escenas</a>
    </div>
  </div>
</div>

<!-- ████ P2: REVELAR TARJETAS ████ -->
<div id="p-revelar" class="pantalla">
  <div class="revelar-wrap">
    <div class="revelar-indicador">
      <div class="rev-paso" id="rev-paso">Jugador 1 de 4</div>
      <div class="rev-dots" id="rev-dots"></div>
    </div>
    <div class="tarjeta-flip" id="tarjeta-flip">
      <div class="tarjeta-frente" id="tarjeta-frente">
        <div class="tarjeta-icono-espera">👁</div>
        <div class="tarjeta-nombre-espera" id="tarjeta-nombre-espera">—</div>
        <div class="tarjeta-instruccion">Pasa el teléfono/PC a esta persona<br>y presiona para ver tu tarjeta</div>
        <button class="btn-ver-tarjeta" id="btn-ver-tarjeta">Ver mi tarjeta</button>
      </div>
      <div class="tarjeta-reverso" id="tarjeta-reverso">
        <!-- Se rellena con JS -->
      </div>
    </div>
    <button class="btn-confirmar-visto" id="btn-confirmar-visto" style="display:none">
      ✓ Listo, ya lo vi
    </button>
  </div>
</div>

<!-- ████ P3: FASE DE PREGUNTAS ████ -->
<div id="p-preguntas" class="pantalla">
  <!-- HUD superior -->
  <div class="hud-top">
    <div id="hud-jugadores" class="hud-jugadores"></div>
    <div class="hud-meta">
      <span class="hud-ronda">Ronda <strong id="hud-ronda-num">1</strong></span>
      <button class="btn-votar-ya" id="btn-votar-ya">Votar ahora</button>
    </div>
  </div>

  <!-- Arena principal -->
  <div class="preguntas-arena">
    <!-- Turno actual -->
    <div class="turno-card">
      <div class="turno-encabezado">
        <div class="turno-avatar" id="turno-avatar"></div>
        <div class="turno-info">
          <div class="turno-etiqueta">Turno de</div>
          <div class="turno-nombre" id="turno-nombre">—</div>
        </div>
        <div class="turno-flecha">→</div>
        <div class="turno-info">
          <div class="turno-etiqueta">Pregunta a</div>
          <div class="turno-interrogado" id="sel-interrogado-display">elige ↓</div>
        </div>
      </div>

      <div class="turno-body">
        <div class="select-wrap">
          <label class="mini-label">¿A quién preguntas?</label>
          <select id="sel-interrogado" class="sel-custom"></select>
        </div>
        <div class="input-wrap">
          <label class="mini-label">Tu pregunta (sobre la escena)</label>
          <textarea id="inp-pregunta" rows="2" placeholder='Ej: "¿Hay agua en esa escena?"'></textarea>
        </div>
        <button id="btn-enviar-pregunta" class="btn-enviar">Hacer pregunta →</button>
      </div>
    </div>

    <!-- Log de preguntas -->
    <div class="log-wrap">
      <div class="log-titulo">Historial de preguntas</div>
      <div id="log-lista" class="log-lista">
        <div class="log-vacio">Las preguntas aparecerán aquí...</div>
      </div>
    </div>
  </div>
</div>

<!-- ████ P4: RESPONDER PREGUNTA ████ -->
<div id="p-responder" class="pantalla">
  <div class="responder-wrap">
    <div class="resp-header">
      <div class="resp-de" id="resp-de">—</div>
      <div class="resp-flecha">pregunta a</div>
      <div class="resp-a" id="resp-a">—</div>
    </div>
    <div class="resp-pregunta" id="resp-pregunta">—</div>
    <div class="resp-input-wrap">
      <textarea id="inp-respuesta" rows="3" placeholder="Escribe tu respuesta..."></textarea>
      <button id="btn-enviar-respuesta" class="btn-enviar">Responder y continuar →</button>
    </div>
    <div class="resp-nota">Recuerda: si eres el espía, responde sin revelar que no sabes la escena</div>
  </div>
</div>

<!-- ████ P5: VOTACIÓN ████ -->
<div id="p-votacion" class="pantalla">
  <div class="votacion-wrap">
    <div class="vot-titulo">
      <span class="vot-icono">⚖</span>
      Votación
    </div>
    <div class="vot-subtitulo">¿Quién crees que es el espía?</div>

    <div class="turno-votante" id="turno-votante">
      <!-- quién vota ahora -->
    </div>

    <div id="candidatos-lista" class="candidatos-lista"></div>

    <div class="votos-progreso">
      <div id="votos-prog-bar" class="votos-prog-bar"></div>
      <div class="votos-prog-label" id="votos-prog-label">0 de 4 votos</div>
    </div>
  </div>
</div>

<!-- ████ P6: ESPÍA ADIVINA ████ -->
<div id="p-espia-adivina" class="pantalla">
  <div class="adivina-wrap">
    <div class="adivina-alerta">
      <div class="adivina-ojo">👁</div>
      <div class="adivina-titulo">¡Te han descubierto!</div>
      <div class="adivina-sub" id="adivina-sub">Pero tienes una última oportunidad...</div>
    </div>
    <div class="adivina-instruccion">
      Escribe el nombre de la escena bíblica.<br>
      Si aciertas, <strong>¡el espía gana!</strong>
    </div>
    <div class="adivina-input-wrap">
      <input type="text" id="inp-adivina" placeholder="Nombre de la escena bíblica..." autocomplete="off">
      <button id="btn-adivinar" class="btn-adivinar">Adivinar escena →</button>
    </div>
    <div class="adivina-pista" id="adivina-pista">
      <!-- pista del libro -->
    </div>
  </div>
</div>

<!-- ████ P7: RESULTADO ████ -->
<div id="p-resultado" class="pantalla">
  <div class="resultado-wrap" id="resultado-wrap">
    <!-- Se rellena con JS -->
  </div>
</div>

<script src="assets/juego.js"></script>
</body>
</html>
