// ══════════════════════════════════════════════
//  EL ESPÍA EN EL TEMPLO — Controlador JS
// ══════════════════════════════════════════════

const COLORES = ['#C02030','#1A5A90','#206840','#7030A0','#A06010','#1A6888','#883020','#306050'];
let estado    = null;
let numJug    = 4;
let votante_idx = 0; // índice del jugador cuyo voto se recoge ahora

// ── Utilidades ────────────────────────────────
function pantalla(id) {
  document.querySelectorAll('.pantalla').forEach(p => {
    p.classList.remove('activa');
    p.style.display = 'none';
  });
  const el = document.getElementById(id);
  el.style.display = 'flex';
  requestAnimationFrame(() => el.classList.add('activa'));
}

async function api(datos) {
  const fd = new FormData();
  for (const [k,v] of Object.entries(datos)) fd.append(k, v);
  const r = await fetch('', { method: 'POST', body: fd });
  return r.json();
}

async function apiGet(params) {
  const qs = new URLSearchParams(params);
  const r = await fetch('?' + qs);
  return r.json();
}

function color(idx) { return COLORES[idx % COLORES.length]; }
function inicial(nombre) { return nombre.charAt(0).toUpperCase(); }

// ══════════════════════════════════════════════
//  P1 — REGISTRO
// ══════════════════════════════════════════════
function renderNombres() {
  document.getElementById('reg-count').textContent = `(${numJug})`;
  document.getElementById('qty-fill').style.width = ((numJug - 2) / 6 * 100) + '%';
  const cont = document.getElementById('reg-nombres');
  cont.innerHTML = '';
  for (let i = 0; i < numJug; i++) {
    const row  = document.createElement('div');
    row.className = 'nombre-row';
    const badge = document.createElement('div');
    badge.className = 'jug-badge';
    badge.style.background = color(i);
    badge.textContent = i + 1;
    const inp = document.createElement('input');
    inp.type = 'text';
    inp.placeholder = `Jugador ${i + 1}`;
    inp.maxLength = 20;
    inp.dataset.idx = i;
    inp.addEventListener('keydown', e => { if (e.key==='Enter') document.getElementById('btn-iniciar').click(); });
    row.appendChild(badge); row.appendChild(inp);
    cont.appendChild(row);
  }
}

document.getElementById('btn-menos').addEventListener('click', () => {
  if (numJug > 4) { numJug--; renderNombres(); }
});
document.getElementById('btn-mas').addEventListener('click', () => {
  if (numJug < 8) { numJug++; renderNombres(); }
});

// Sincronizar opciones de dificultad visual
document.querySelectorAll('.dif-opt').forEach(el => {
  el.addEventListener('click', () => {
    document.querySelectorAll('.dif-opt').forEach(x => x.classList.remove('activa'));
    el.classList.add('activa');
  });
});

document.getElementById('btn-iniciar').addEventListener('click', async () => {
  const inputs  = document.querySelectorAll('.nombre-row input');
  const nombres = [];
  inputs.forEach((inp, i) => nombres.push(inp.value.trim() || `Jugador ${i+1}`));
  const dif = document.querySelector('input[name="dif"]:checked')?.value || 'todos';

  const data = await api({ accion: 'iniciar', nombres: JSON.stringify(nombres), dificultad: dif });
  if (data.error) { alert(data.error); return; }
  estado = data.estado;
  iniciarRevelar();
});

renderNombres();

// ══════════════════════════════════════════════
//  P2 — REVELAR TARJETAS (una por una, privado)
// ══════════════════════════════════════════════
function iniciarRevelar() {
  pantalla('p-revelar');
  renderRevelar();
}

function renderRevelar() {
  const idx = estado.revelar_idx;
  const jug = estado.jugadores[idx];
  if (!jug) return;

  // Dots
  const dotsEl = document.getElementById('rev-dots');
  dotsEl.innerHTML = '';
  estado.jugadores.forEach((_, i) => {
    const d = document.createElement('div');
    d.className = 'rev-dot' + (i < idx ? ' done' : i === idx ? ' activo' : '');
    dotsEl.appendChild(d);
  });

  document.getElementById('rev-paso').textContent = `Jugador ${idx + 1} de ${estado.jugadores.length}`;
  document.getElementById('tarjeta-nombre-espera').textContent = jug.nombre;

  // Resetear
  document.getElementById('tarjeta-frente').style.display = 'block';
  document.getElementById('tarjeta-reverso').style.display = 'none';
  document.getElementById('btn-confirmar-visto').style.display = 'none';
}

document.getElementById('btn-ver-tarjeta').addEventListener('click', async () => {
  const idx    = estado.revelar_idx;
  const nombre = estado.jugadores[idx].nombre;
  const data   = await apiGet({ tarjeta: nombre });

  const frente  = document.getElementById('tarjeta-frente');
  const reverso = document.getElementById('tarjeta-reverso');

  frente.style.display = 'none';
  reverso.style.display = 'block';
  document.getElementById('btn-confirmar-visto').style.display = 'block';

  // Construir contenido de tarjeta
  if (data.tipo === 'espia') {
    reverso.className = 'tarjeta-reverso tc-espia';
    reverso.innerHTML = `
      <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem">
        <span class="tc-rol-badge badge-espia">Eres el espía</span>
        <span class="tc-libro">${data.libro || ''}</span>
      </div>
      <div class="tc-espia-titulo">ERES<br>EL ESPÍA</div>
      <div class="tc-espia-sub">No conoces la escena bíblica.<br>Haz preguntas sin delatarte.</div>
      <div class="tc-espia-tips">
        <div class="tc-tip"><span>🎭</span><span>Responde con preguntas vagas o ambiguas</span></div>
        <div class="tc-tip"><span>👀</span><span>Observa las reacciones de los demás</span></div>
        <div class="tc-tip"><span>🧠</span><span>Si te descubren, intenta adivinar la escena</span></div>
        <div class="tc-tip"><span>🏆</span><span>Ganas si no te votan o si adivinas la escena</span></div>
      </div>`;
  } else {
    reverso.className = 'tarjeta-reverso tc-agente';
    const pistasHtml = (data.pistas || []).map(p =>
      `<div class="tc-pista"><div class="tc-pista-dot"></div><span>${p}</span></div>`
    ).join('');
    reverso.innerHTML = `
      <div class="tc-agente-header">
        <span class="tc-rol-badge badge-agente">Agente del templo</span>
        <span class="tc-libro">${data.libro || ''}</span>
      </div>
      <div class="tc-escena-titulo">${data.titulo}</div>
      <div class="tc-pistas-titulo">Pistas para tus respuestas</div>
      <div class="tc-pistas">${pistasHtml}</div>`;
  }
});

document.getElementById('btn-confirmar-visto').addEventListener('click', async () => {
  const data = await api({ accion: 'confirmar_visto' });
  estado = data.estado;
  if (estado.fase === 'preguntas') {
    iniciarPreguntas();
  } else {
    renderRevelar();
  }
});

// ══════════════════════════════════════════════
//  P3 — FASE DE PREGUNTAS
// ══════════════════════════════════════════════
let pregunta_pendiente = null; // { preguntador, interrogado, pregunta }

function iniciarPreguntas() {
  pantalla('p-preguntas');
  renderPreguntas();
}

function renderPreguntas() {
  if (!estado) return;

  // HUD jugadores
  const hudEl = document.getElementById('hud-jugadores');
  hudEl.innerHTML = '';
  estado.jugadores.forEach((j, i) => {
    const chip = document.createElement('div');
    chip.className = 'hud-chip' + (i === estado.turno_idx ? ' activo' : '');
    chip.innerHTML = `
      <div class="hud-chip-dot" style="background:${color(j.color_idx)}"></div>
      <span class="hud-chip-nom">${j.nombre}</span>`;
    hudEl.appendChild(chip);
  });

  document.getElementById('hud-ronda-num').textContent = estado.ronda;

  // Jugador activo
  const jActivo = estado.jugadores[estado.turno_idx];
  const avatarEl = document.getElementById('turno-avatar');
  avatarEl.style.background = color(jActivo.color_idx);
  avatarEl.textContent = inicial(jActivo.nombre);
  document.getElementById('turno-nombre').textContent = jActivo.nombre;

  // Select de interrogado (todos menos el activo)
  const sel = document.getElementById('sel-interrogado');
  sel.innerHTML = '';
  estado.jugadores.forEach((j, i) => {
    if (i !== estado.turno_idx && !j.eliminado) {
      const opt = document.createElement('option');
      opt.value = j.nombre;
      opt.textContent = j.nombre;
      sel.appendChild(opt);
    }
  });
  actualizarInterrogadoDisplay();
  sel.addEventListener('change', actualizarInterrogadoDisplay);

  // Log
  renderLog();
}

function actualizarInterrogadoDisplay() {
  const sel = document.getElementById('sel-interrogado');
  document.getElementById('sel-interrogado-display').textContent = sel.value || '—';
}

function renderLog() {
  const lista = document.getElementById('log-lista');
  if (!estado.preguntas_log?.length) {
    lista.innerHTML = '<div class="log-vacio">Las preguntas aparecerán aquí...</div>';
    return;
  }
  lista.innerHTML = '';
  [...estado.preguntas_log].reverse().forEach(item => {
    const div = document.createElement('div');
    div.className = 'log-item';
    div.innerHTML = `
      <div class="log-meta"><strong>${item.preguntador}</strong> → <strong>${item.interrogado}</strong></div>
      <div class="log-preg">"${item.pregunta}"</div>
      ${item.respuesta ? `<div class="log-resp">↪ ${item.respuesta}</div>` : '<div style="font-size:.75rem;color:var(--gris-cla);font-style:italic">Esperando respuesta...</div>'}`;
    lista.appendChild(div);
  });
}

document.getElementById('btn-enviar-pregunta').addEventListener('click', async () => {
  const jActivo    = estado.jugadores[estado.turno_idx];
  const interrogado= document.getElementById('sel-interrogado').value;
  const pregunta   = document.getElementById('inp-pregunta').value.trim();
  if (!pregunta) { document.getElementById('inp-pregunta').focus(); return; }

  pregunta_pendiente = { preguntador: jActivo.nombre, interrogado, pregunta };

  await api({ accion: 'log_pregunta', preguntador: jActivo.nombre, interrogado, pregunta });

  // Mostrar pantalla de respuesta
  document.getElementById('resp-de').textContent    = jActivo.nombre;
  document.getElementById('resp-a').textContent     = interrogado;
  document.getElementById('resp-pregunta').textContent = `"${pregunta}"`;
  document.getElementById('inp-respuesta').value    = '';
  pantalla('p-responder');
  setTimeout(() => document.getElementById('inp-respuesta').focus(), 300);
});

document.getElementById('btn-votar-ya').addEventListener('click', async () => {
  if (!confirm('¿Iniciar votación ahora? Ya no habrá más preguntas.')) return;
  const data = await api({ accion: 'iniciar_votacion' });
  estado = data.estado;
  votante_idx = 0;
  iniciarVotacion();
});

// P4 — RESPONDER PREGUNTA
document.getElementById('btn-enviar-respuesta').addEventListener('click', async () => {
  const resp = document.getElementById('inp-respuesta').value.trim();
  if (!resp) { document.getElementById('inp-respuesta').focus(); return; }

  document.getElementById('inp-pregunta').value = '';
  const data = await api({ accion: 'log_respuesta', respuesta: resp });
  estado = data.estado;
  pantalla('p-preguntas');
  renderPreguntas();
});

// ══════════════════════════════════════════════
//  P5 — VOTACIÓN
// ══════════════════════════════════════════════
function iniciarVotacion() {
  pantalla('p-votacion');
  renderVotacion();
}

function renderVotacion() {
  const activos = estado.jugadores.filter(j => !j.eliminado);
  if (votante_idx >= activos.length) return;
  const votante = activos[votante_idx];

  // Quién vota ahora
  const tvEl = document.getElementById('turno-votante');
  tvEl.innerHTML = `
    <div class="tv-label">Vota ahora</div>
    <div class="tv-nombre" style="color:${color(votante.color_idx)}">${votante.nombre}</div>`;

  // Candidatos (todos menos el votante)
  const lista = document.getElementById('candidatos-lista');
  lista.innerHTML = '';
  activos.forEach(j => {
    if (j.nombre === votante.nombre) return;
    const btn = document.createElement('button');
    btn.className = 'candidato-btn';
    btn.innerHTML = `
      <div class="cand-dot" style="background:${color(j.color_idx)}"></div>
      <span class="cand-nom">${j.nombre}</span>
      <span class="cand-votos">${j.votos} voto${j.votos !== 1 ? 's' : ''}</span>`;
    btn.addEventListener('click', () => emitirVoto(votante.nombre, j.nombre));
    lista.appendChild(btn);
  });

  // Progreso
  const total = activos.length;
  const emitidos = estado.votos_emitidos;
  const fill = document.querySelector('.votos-prog-fill');
  if (!fill) {
    document.getElementById('votos-prog-bar').innerHTML = '<div class="votos-prog-fill"></div>';
  }
  document.querySelector('.votos-prog-fill').style.width = (emitidos / total * 100) + '%';
  document.getElementById('votos-prog-label').textContent = `${emitidos} de ${total} votos`;
}

async function emitirVoto(votante, acusado) {
  const data = await api({ accion: 'votar', votante, acusado });
  estado = data.estado;

  votante_idx++;
  const activos = estado.jugadores.filter(j => !j.eliminado);

  if (estado.fase === 'espia_adivinando') {
    mostrarEspiaAdivina();
    return;
  }
  if (estado.fase === 'resultado') {
    mostrarResultado();
    return;
  }

  if (votante_idx < activos.length) {
    renderVotacion();
  } else {
    mostrarResultado();
  }
}

// ══════════════════════════════════════════════
//  P6 — ESPÍA ADIVINA
// ══════════════════════════════════════════════
function mostrarEspiaAdivina() {
  pantalla('p-espia-adivina');
  const espiaNom = estado.jugadores.find(j => j.es_espia)?.nombre
                  || estado.espia_nombre || '?';
  document.getElementById('adivina-sub').textContent =
    `${estado.mas_votado || espiaNom}, tienes una última oportunidad...`;
  document.getElementById('adivina-pista').textContent =
    `Pista: Libro → ${estado.escena_libro || '—'} · Dificultad: ${estado.escena_dificultad || '—'}`;
  document.getElementById('inp-adivina').value = '';
  document.getElementById('inp-adivina').focus();
}

document.getElementById('btn-adivinar').addEventListener('click', async () => {
  const intento = document.getElementById('inp-adivina').value.trim();
  if (!intento) return;
  const data = await api({ accion: 'espia_adivina', intento });
  estado = data.estado;
  mostrarResultado();
});

document.getElementById('inp-adivina').addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('btn-adivinar').click();
});

// ══════════════════════════════════════════════
//  P7 — RESULTADO
// ══════════════════════════════════════════════
function mostrarResultado() {
  pantalla('p-resultado');
  const wrap     = document.getElementById('resultado-wrap');
  const ganador  = estado.ganador;
  const espiaNom = estado.espia_nombre;
  const escena   = estado.escena_titulo;
  const adivinado= estado.espia_adivinado;

  let tituloBanner, iconoBanner, clasesBanner, subtitulo;

  if (ganador === 'espia') {
    if (adivinado) {
      tituloBanner = '¡El espía adivinó!'; iconoBanner = '👁';
      subtitulo = `${espiaNom} descubrió la escena y gana la partida`;
    } else {
      tituloBanner = '¡El espía escapó!'; iconoBanner = '🕵';
      subtitulo = `${espiaNom} no fue descubierto — el grupo pierde`;
    }
    clasesBanner = 'res-banner espia-gana';
  } else {
    tituloBanner = '¡Espía atrapado!'; iconoBanner = '⚖';
    subtitulo = `${espiaNom} fue descubierto por el grupo`;
    clasesBanner = 'res-banner grupo-gana';
  }

  // Ranking con puntos
  const jugadoresConPts = estado.jugadores.map(j => {
    let pts = 0;
    if (j.es_espia && ganador === 'espia') pts = 3;
    else if (!j.es_espia && ganador === 'grupo') pts = 1;
    return { ...j, pts };
  }).sort((a,b) => b.pts - a.pts);

  const filasJug = jugadoresConPts.map(j => `
    <div class="res-jug-fila">
      <div class="rj-dot" style="background:${color(j.color_idx)}"></div>
      <span class="rj-nom">${j.nombre}</span>
      <span class="rj-rol ${j.es_espia ? 'rj-espia' : 'rj-agente'}">${j.es_espia ? '👁 Espía' : '⚔ Agente'}</span>
      <span class="rj-pts">+${j.pts} pts</span>
    </div>`).join('');

  wrap.innerHTML = `
    <div class="${clasesBanner}">
      <div class="res-icon">${iconoBanner}</div>
      <div class="res-titulo ${ganador === 'espia' ? 'rojo' : 'verde'}">${tituloBanner}</div>
      <div class="res-sub">${subtitulo}</div>
    </div>

    <div class="res-escena">
      <div class="res-escena-label">La escena secreta era</div>
      <div class="res-escena-titulo">${escena || '—'}</div>
      <div class="res-escena-desc">${estado.escena_desc || ''}</div>
    </div>

    <div class="res-espia-reveal">
      <div class="res-espia-label">El espía era</div>
      <div class="res-espia-nombre" style="color:${color(estado.jugadores.find(j=>j.es_espia)?.color_idx ?? 0)}">${espiaNom}</div>
    </div>

    <div class="res-jugadores">${filasJug}</div>

    <div class="res-acciones">
      <button class="btn-nueva" id="btn-nueva-partida">Nueva partida</button>
      <a href="admin/historial.php" class="btn-hist">Ver historial</a>
    </div>`;

  document.getElementById('btn-nueva-partida').addEventListener('click', async () => {
    await api({ accion: 'nueva_partida' });
    location.reload();
  });

  lanzarConfeti(ganador);
}

// ── Confeti ───────────────────────────────────
function lanzarConfeti(ganador) {
  const cols = ganador === 'espia'
    ? ['#C02030','#E83040','#FF6070','#7030A0','#F0C030']
    : ['#206840','#30A060','#F0C030','#FDE68A','#1A5A90'];
  for (let i = 0; i < 60; i++) {
    setTimeout(() => {
      const el = document.createElement('div');
      el.style.cssText = `position:fixed;top:-10px;left:${Math.random()*100}vw;width:${6+Math.random()*8}px;height:${6+Math.random()*8}px;background:${cols[Math.floor(Math.random()*cols.length)]};border-radius:2px;pointer-events:none;z-index:999;animation:confeti-cae ${2+Math.random()*2}s linear forwards`;
      document.body.appendChild(el);
      setTimeout(()=>el.remove(), 4200);
    }, i * 50);
  }
  if (!document.getElementById('confeti-css')) {
    const s = document.createElement('style');
    s.id = 'confeti-css';
    s.textContent = '@keyframes confeti-cae{from{transform:translateY(-10px) rotate(0);opacity:1}to{transform:translateY(110vh) rotate(720deg);opacity:0}}';
    document.head.appendChild(s);
  }
}
